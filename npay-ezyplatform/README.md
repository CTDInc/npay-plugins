# NPay Ezyplatform Plugin

Plugin tích hợp cổng thanh toán **NPay** cho [Ezyplatform](https://ezyplatform.com/).
Hỗ trợ sinh mã QR động (qua `qr.npay.vn`) và nhận webhook xác nhận giao dịch ngân hàng từ `api.npay.vn`.

## Tính năng

- Sinh URL ảnh QR VietQR động cho từng đơn hàng (kèm số tiền, nội dung CK).
- Endpoint `POST /webhook/npay` xác thực bằng `Authorization: Apikey <token>` và tự đối soát đơn.
- Tách order code từ trường `code` của payload hoặc fallback parse từ `content`.
- Cấu hình qua `application.properties` chuẩn Spring Boot.

## Yêu cầu

- Java 17+
- Maven 3.9+
- Ezyplatform host application (Spring container).

## Build

```bash
mvn clean package
```

Sản phẩm: `target/npay-ezyplatform-1.0.0.jar` (đã shade jackson-databind).

## Cài đặt

1. Copy file jar vào thư mục plugin của Ezyplatform:
   ```bash
   cp target/npay-ezyplatform-1.0.0.jar $EZYPLATFORM_HOME/plugins/
   ```
2. Restart Ezyplatform.
3. Kiểm tra log thấy dòng `[NPay] Plugin khởi tạo thành công`.

## Cấu hình

Thêm vào `application.properties` của Ezyplatform host:

```properties
npay.api-token=your-webhook-api-key
npay.webhook-secret=your-webhook-secret
npay.account-number=0123456789
npay.bank-bin=970422
npay.account-holder=CONG TY NPAY
npay.qr-template=compact
npay.api-base-url=https://api.npay.vn
npay.qr-base-url=https://qr.npay.vn
npay.merchant-portal=https://npay.vn
```

| Khoá | Mô tả |
|------|------|
| `npay.api-token` | API key của webhook (`Authorization: Apikey <token>`) |
| `npay.webhook-secret` | (Tuỳ chọn) webhook secret — kiểm `X-Npay-Signature` |
| `npay.webhook-tolerance-seconds` | Độ lệch tối đa của `X-Npay-Timestamp`, mặc định `300` |
| `npay.account-number` | Số tài khoản nhận tiền |
| `npay.bank-bin` | Mã BIN ngân hàng (vd `970422` = MB Bank) hoặc mã như `mbbank`, `VCB` |
| `npay.account-holder` | Tên chủ tài khoản hiển thị |
| `npay.qr-template` | `qr_only` = chỉ mã QR (`/qrpay`), còn lại = thẻ VietQR (`/qrcard`) |

## Cấu hình webhook trên NPay

Tại dashboard <https://npay.vn>, thêm webhook:

- **URL**: `https://<your-domain>/webhook/npay`
- **Method**: `POST`, **Content-Type**: `application/json`
- **Xác thực API Key**: NPay gửi `Authorization: Apikey <key>` → đặt vào `npay.api-token`
- **Ký request** (tuỳ chọn): chép webhook secret vào `npay.webhook-secret`; NPay gửi
  `X-Npay-Signature` = hex HMAC-SHA256 của raw body (không tiền tố `sha256=`) + `X-Npay-Timestamp`.

Request hợp lệ khi khớp **một trong hai**. `id` trong payload là chuỗi `tx_…` (không phải số);
`referenceCode` có thể `null`. Giao dịch không có mã đơn / chuyển thiếu trả 200 để NPay không gửi lại.

## Sử dụng trong code

```java
@Autowired NPayPlugin npay;

public String checkout(Order order) {
    return npay.processPayment(order.getCode(), order.getTotalAmount());
    // -> https://qr.npay.vn/qrcard?ma_bin=...&tai_khoan=...&so_tien=...&noi_dung=...
}
```

Host application cần cung cấp một bean implement `vn.npay.ezyplatform.OrderService`:

```java
@Service
public class MyOrderService implements OrderService {
    @Override
    public void markPaid(String orderCode, long amount, String referenceCode) {
        markPaid(orderCode, amount, referenceCode, null);
    }

    @Override
    public void markPaid(String orderCode, long amount, String referenceCode, String transactionId) {
        // NPay có thể gửi lại cùng giao dịch: ghi nhận theo transactionId ("tx_…") một lần.
    }

    @Override
    public long getAmountDue(String orderCode) {
        return 0L; // > 0 thì webhook bỏ qua giao dịch chuyển thiếu
    }
}
```

## Thay đổi

### 1.1.0

- `NPayWebhookPayload.id` đổi `Long` → `String` (NPay gửi `tx_…`; bản cũ lỗi parse mọi webhook).
- `OrderService.markPaid(..., String transactionId)` để host chống trùng theo id NPay.
- QR chuyển sang `https://qr.npay.vn/qrcard` / `/qrpay` (`/img` không còn).
- Thêm `npay.webhook-secret` (kiểm `X-Npay-Signature`), kiểm số tiền qua `getAmountDue`.

## Test

```bash
mvn test
```

## Cấu trúc

```
npay-ezyplatform/
├── pom.xml
├── src/main/java/vn/npay/ezyplatform/
│   ├── NPayPlugin.java              # Entry plugin
│   ├── NPayConfig.java              # @ConfigurationProperties(prefix="npay")
│   ├── NPayQrService.java           # Builder URL qr.npay.vn
│   ├── NPayWebhookController.java   # POST /webhook/npay
│   ├── NPayWebhookPayload.java      # DTO JSON
│   └── OrderService.java            # SPI cho host platform
├── src/main/resources/
│   ├── META-INF/ezyplugin.properties
│   ├── application.properties
│   └── templates/payment-instructions.html
└── src/test/java/vn/npay/ezyplatform/NPayQrServiceTest.java
```

## License

Proprietary © NPay. Tham khảo: <https://npay.vn>.
