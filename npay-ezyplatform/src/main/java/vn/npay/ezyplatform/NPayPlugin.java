package vn.npay.ezyplatform;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Component;

import jakarta.annotation.PostConstruct;

/**
 * Plugin chính tích hợp cổng NPay vào Ezyplatform.
 * Được khởi tạo bởi container Ezyplatform thông qua manifest
 * META-INF/ezyplugin.properties (plugin.entry).
 */
@Component
public class NPayPlugin {

    private static final Logger log = LoggerFactory.getLogger(NPayPlugin.class);

    private final NPayConfig config;
    private final NPayQrService qrService;

    @Autowired
    public NPayPlugin(NPayConfig config, NPayQrService qrService) {
        this.config = config;
        this.qrService = qrService;
    }

    @PostConstruct
    public void init() {
        log.info("[NPay] Plugin khởi tạo thành công. Bank={}, Acc={}, Holder={}",
                config.getBankBin(), mask(config.getAccountNumber()), config.getAccountHolder());
        if (config.getApiToken() == null || config.getApiToken().isBlank()) {
            log.warn("[NPay] Thiếu apiToken — webhook sẽ từ chối tất cả request!");
        }
    }

    /**
     * Tạo URL ảnh QR để chuyển hướng khách hàng đến trang thanh toán.
     *
     * @param orderId mã đơn hàng (dùng làm nội dung chuyển khoản)
     * @param amount  số tiền VND
     * @return URL ảnh QR động trên qr.npay.vn
     */
    public String processPayment(String orderId, long amount) {
        if (orderId == null || orderId.isBlank()) {
            throw new IllegalArgumentException("orderId không được rỗng");
        }
        if (amount <= 0) {
            throw new IllegalArgumentException("amount phải > 0");
        }
        String url = qrService.buildQrUrl(orderId, amount);
        log.info("[NPay] Tạo QR cho đơn {} ({} VND) -> {}", orderId, amount, url);
        return url;
    }

    public NPayConfig getConfig() {
        return config;
    }

    private static String mask(String s) {
        if (s == null || s.length() < 4) return "****";
        return "****" + s.substring(s.length() - 4);
    }
}
