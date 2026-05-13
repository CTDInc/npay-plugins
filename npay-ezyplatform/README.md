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
npay.api-token=your-secret-webhook-token
npay.account-number=0123456789
npay.bank-bin=970422
npay.account-holder=CONG TY NPAY
npay.qr-template=compact
npay.api-base-url=https://api.npay.vn
npay.qr-base-url=https://qr.npay.vn
npay.merchant-portal=https://my.npay.vn
```

| Khoá | Mô tả |
|------|------|
| `npay.api-token` | Token để xác thực webhook từ NPay |
| `npay.account-number` | Số tài khoản nhận tiền |
| `npay.bank-bin` | Mã BIN ngân hàng (vd `970422` = MB Bank) |
| `npay.account-holder` | Tên chủ tài khoản hiển thị |
| `npay.qr-template` | Mẫu QR: `compact`, `compact2`, `qr_only`, `print` |

## Cấu hình webhook trên `my.npay.vn`

Tại trang quản trị merchant <https://my.npay.vn>, thêm webhook:

- **URL**: `https://<your-domain>/webhook/npay`
- **Method**: `POST`
- **Header**: `Authorization: Apikey <giá trị npay.api-token>`
- **Content-Type**: `application/json`

## Sử dụng trong code

```java
@Autowired NPayPlugin npay;

public String checkout(Order order) {
    return npay.processPayment(order.getCode(), order.getTotalAmount());
    // -> https://qr.npay.vn/img?acc=...&bank=...&amount=...&des=...
}
```

Host application cần cung cấp một bean implement `vn.npay.ezyplatform.OrderService`:

```java
@Service
public class MyOrderService implements OrderService {
    @Override
    public void markPaid(String orderCode, long amount, String referenceCode) {
        // cập nhật trạng thái đơn hàng
    }
}
```

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
