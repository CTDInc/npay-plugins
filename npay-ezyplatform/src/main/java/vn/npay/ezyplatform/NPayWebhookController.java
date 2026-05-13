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

import java.util.Map;

/**
 * Controller nhận webhook giao dịch từ NPay (api.npay.vn).
 *
 * NPay sẽ POST JSON với header: {@code Authorization: Apikey <apiToken>}.
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
            @RequestBody String body) {

        if (!isAuthorized(auth)) {
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

        log.info("[NPay] Webhook nhận giao dịch: code={}, amount={}, ref={}",
                payload.getCode(), payload.getTransferAmount(), payload.getReferenceCode());

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
            return ResponseEntity.ok(Map.of("success", true, "message", "No order code"));
        }

        try {
            orderService.markPaid(orderCode, payload.getTransferAmount(), payload.getReferenceCode());
            return ResponseEntity.ok(Map.of("success", true));
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
        String expected = "Apikey " + token;
        return expected.equals(auth) || token.equals(auth);
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
