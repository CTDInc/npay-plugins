package vn.npay.ezyplatform;

import com.fasterxml.jackson.databind.ObjectMapper;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestHeader;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.util.HashMap;
import java.util.HexFormat;
import java.util.Locale;
import java.util.Map;

/**
 * Controller nhận webhook giao dịch từ NPay (api.npay.vn).
 *
 * Xác thực: {@code Authorization: Apikey <apiToken>} hoặc, khi cấu hình
 * {@code npay.webhook-secret}, {@code X-Npay-Signature} = hex HMAC-SHA256 của raw
 * body (kèm {@code X-Npay-Timestamp} lệch tối đa {@code webhook-tolerance-seconds}).
 * Giao dịch không khớp đơn vẫn trả 200 để NPay không gửi lại mãi.
 */
@RestController
@RequestMapping("/webhook")
public class NPayWebhookController {

    private static final Logger log = LoggerFactory.getLogger(NPayWebhookController.class);

    private final NPayConfig config;
    private final OrderService orderService;
    private final ObjectMapper mapper = new ObjectMapper();

    @Autowired
    public NPayWebhookController(NPayConfig config, OrderService orderService) {
        this.config = config;
        this.orderService = orderService;
    }

    @PostMapping("/npay")
    public ResponseEntity<Map<String, Object>> handle(
            @RequestHeader(value = "Authorization", required = false) String auth,
            @RequestHeader(value = "X-Npay-Signature", required = false) String signature,
            @RequestHeader(value = "X-Npay-Timestamp", required = false) String timestamp,
            @RequestBody String body) {

        if (!isAuthorized(auth) && !isSignatureValid(body, signature, timestamp)) {
            log.warn("[NPay] Webhook bị từ chối: Authorization không hợp lệ");
            return ResponseEntity.status(401).body(Map.of(
                    "success", false,
                    "message", "Unauthorized"
            ));
        }

        NPayWebhookPayload payload;
        try {
            payload = mapper.readValue(body, NPayWebhookPayload.class);
        } catch (Exception e) {
            log.error("[NPay] Webhook payload không hợp lệ: {}", e.getMessage());
            return ResponseEntity.badRequest().body(Map.of(
                    "success", false,
                    "message", "Invalid JSON payload"
            ));
        }

        log.info("[NPay] Webhook nhận giao dịch: id={}, code={}, amount={}, ref={}",
                payload.getId(), payload.getCode(), payload.getTransferAmount(), payload.getReferenceCode());

        // Chỉ xử lý giao dịch tiền vào
        if (!"in".equalsIgnoreCase(payload.getTransferType())) {
            return ResponseEntity.ok(Map.of("success", true, "message", "Ignored non-incoming"));
        }

        String orderCode = payload.getCode();
        if (orderCode == null || orderCode.isBlank()) {
            // Fallback: parse từ nội dung chuyển khoản
            orderCode = extractOrderCode(payload.getContent());
        }
        if (orderCode == null) {
            return ResponseEntity.ok(Map.of("success", true, "message", "Ignored: no order code"));
        }

        try {
            long due = orderService.getAmountDue(orderCode);
            if (due > 0 && payload.getTransferAmount() < due) {
                log.warn("[NPay] Đơn {} chuyển thiếu: {} < {}", orderCode, payload.getTransferAmount(), due);
                return ResponseEntity.ok(Map.of("success", true, "message", "Ignored: underpaid"));
            }
            orderService.markPaid(orderCode, payload.getTransferAmount(), payload.getReferenceCode(), payload.getId());
            Map<String, Object> ok = new HashMap<>();
            ok.put("success", true);
            ok.put("transaction_id", payload.getId());
            return ResponseEntity.ok(ok);
        } catch (Exception e) {
            log.error("[NPay] Lỗi khi cập nhật đơn {}: {}", orderCode, e.getMessage(), e);
            return ResponseEntity.status(500).body(Map.of(
                    "success", false,
                    "message", e.getMessage()
            ));
        }
    }

    private boolean isAuthorized(String auth) {
        String token = config.getApiToken();
        if (token == null || token.isBlank()) return false;
        if (auth == null || auth.isBlank()) return false;
        String trimmed = auth.trim();
        if (trimmed.regionMatches(true, 0, "Apikey ", 0, 7)) {
            return constantTimeEquals(token, trimmed.substring(7).trim());
        }
        return constantTimeEquals(token, trimmed);
    }

    boolean isSignatureValid(String body, String signature, String timestamp) {
        String secret = config.getWebhookSecret();
        if (secret == null || secret.isBlank() || signature == null || signature.isBlank() || body == null) {
            return false;
        }
        String expected = hmacSha256Hex(secret, body);
        if (!constantTimeEquals(expected, signature.trim().toLowerCase(Locale.ROOT))) {
            return false;
        }
        if (timestamp == null || timestamp.isBlank()) {
            return true;
        }
        try {
            long ts = Long.parseLong(timestamp.trim());
            return Math.abs(System.currentTimeMillis() / 1000 - ts) <= config.getWebhookToleranceSeconds();
        } catch (NumberFormatException e) {
            return false;
        }
    }

    static String hmacSha256Hex(String secret, String body) {
        try {
            Mac mac = Mac.getInstance("HmacSHA256");
            mac.init(new SecretKeySpec(secret.getBytes(StandardCharsets.UTF_8), "HmacSHA256"));
            return HexFormat.of().formatHex(mac.doFinal(body.getBytes(StandardCharsets.UTF_8)));
        } catch (Exception e) {
            throw new IllegalStateException("HmacSHA256 unavailable", e);
        }
    }

    private static boolean constantTimeEquals(String a, String b) {
        return MessageDigest.isEqual(
                a.getBytes(StandardCharsets.UTF_8), b.getBytes(StandardCharsets.UTF_8));
    }

    static String extractOrderCode(String content) {
        if (content == null) return null;
        // Tìm pattern dạng NPAY<digits> hoặc ORDER<digits>
        String upper = content.toUpperCase();
        for (String prefix : new String[]{"NPAY", "ORDER", "DH"}) {
            int idx = upper.indexOf(prefix);
            if (idx >= 0) {
                StringBuilder sb = new StringBuilder(prefix);
                int i = idx + prefix.length();
                while (i < upper.length() && Character.isLetterOrDigit(upper.charAt(i))) {
                    sb.append(upper.charAt(i));
                    i++;
                }
                if (sb.length() > prefix.length()) return sb.toString();
            }
        }
        return null;
    }
}
