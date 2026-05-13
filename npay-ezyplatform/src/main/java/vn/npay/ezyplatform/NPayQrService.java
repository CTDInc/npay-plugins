package vn.npay.ezyplatform;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Service;

import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;

/**
 * Service sinh URL ảnh QR động trên qr.npay.vn.
 *
 * Mẫu URL:
 * <pre>
 * https://qr.npay.vn/img?acc=&lt;acc&gt;&bank=&lt;bin&gt;&amount=&lt;amt&gt;&des=&lt;noi_dung&gt;&template=&lt;tpl&gt;
 * </pre>
 */
@Service
public class NPayQrService {

    private final NPayConfig config;

    @Autowired
    public NPayQrService(NPayConfig config) {
        this.config = config;
    }

    public String buildQrUrl(String orderCode, long amount) {
        return buildQrUrl(
                config.getQrBaseUrl(),
                config.getAccountNumber(),
                config.getBankBin(),
                amount,
                orderCode,
                config.getAccountHolder(),
                config.getQrTemplate()
        );
    }

    /**
     * Static builder — tách ra để dễ unit test, không phụ thuộc Spring.
     */
    public static String buildQrUrl(String baseUrl,
                                    String accountNumber,
                                    String bankBin,
                                    long amount,
                                    String orderCode,
                                    String accountHolder,
                                    String template) {
        if (baseUrl == null || baseUrl.isBlank()) {
            baseUrl = "https://qr.npay.vn";
        }
        String base = baseUrl.endsWith("/") ? baseUrl.substring(0, baseUrl.length() - 1) : baseUrl;

        StringBuilder sb = new StringBuilder(base).append("/img?");
        sb.append("acc=").append(enc(accountNumber));
        sb.append("&bank=").append(enc(bankBin));
        if (amount > 0) {
            sb.append("&amount=").append(amount);
        }
        if (orderCode != null && !orderCode.isBlank()) {
            sb.append("&des=").append(enc(orderCode));
        }
        if (accountHolder != null && !accountHolder.isBlank()) {
            sb.append("&accountName=").append(enc(accountHolder));
        }
        if (template != null && !template.isBlank()) {
            sb.append("&template=").append(enc(template));
        }
        return sb.toString();
    }

    private static String enc(String s) {
        if (s == null) return "";
        return URLEncoder.encode(s, StandardCharsets.UTF_8);
    }
}
