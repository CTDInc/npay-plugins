package vn.npay.ezyplatform;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Service;

import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.Locale;
import java.util.Map;

/**
 * Service sinh URL ảnh QR động trên qr.npay.vn (dịch vụ gen-qr).
 *
 * Mẫu URL:
 * <pre>
 * https://qr.npay.vn/qrcard?ma_bin=&lt;bin&gt;&amp;tai_khoan=&lt;acc&gt;&amp;so_tien=&lt;amt&gt;&amp;noi_dung=&lt;noi_dung&gt;&amp;chu_tai_khoan=&lt;ten&gt;
 * </pre>
 * Template {@code qr_only} dùng {@code /qrpay} (chỉ mã QR). Ngân hàng nhận BIN
 * ({@code ma_bin}) hoặc mã ngân hàng ({@code ngan_hang}, vd "mbbank", "VCB").
 */
@Service
public class NPayQrService {

    private static final Map<String, String> BANK_ALIASES = Map.ofEntries(
            Map.entry("vcb", "vietcombank"), Map.entry("tcb", "techcombank"),
            Map.entry("ctg", "vietinbank"), Map.entry("icb", "vietinbank"),
            Map.entry("mb", "mbbank"), Map.entry("vpb", "vpbank"),
            Map.entry("tpb", "tpbank"), Map.entry("stb", "sacombank"),
            Map.entry("hdb", "hdbank"), Map.entry("eib", "eximbank"),
            Map.entry("vba", "agribank"), Map.entry("agr", "agribank"),
            Map.entry("lpb", "lienvietpostbank"), Map.entry("lpbank", "lienvietpostbank"),
            Map.entry("nab", "namabank"), Map.entry("abb", "abbank"),
            Map.entry("bab", "bacabank"), Map.entry("pvcb", "pvcombank"),
            Map.entry("seab", "seabank"), Map.entry("klb", "kienlongbank"),
            Map.entry("vab", "vietabank"), Map.entry("sgicb", "saigonbank"),
            Map.entry("bvb", "banviet")
    );

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
        String base = baseUrl.replaceAll("/+$", "").replaceAll("/(img|qrpay|qrcard)$", "");
        boolean bareQr = "qr_only".equals(template) || "qronly".equals(template);

        StringBuilder sb = new StringBuilder(base).append(bareQr ? "/qrpay?" : "/qrcard?");
        String bank = bankBin == null ? "" : bankBin.trim().toLowerCase(Locale.ROOT).replaceAll("[\\s_-]+", "");
        if (bank.matches("\\d{6}")) {
            sb.append("ma_bin=").append(enc(bank));
        } else {
            sb.append("ngan_hang=").append(enc(BANK_ALIASES.getOrDefault(bank, bank)));
        }
        sb.append("&tai_khoan=").append(enc(accountNumber));
        if (amount > 0) {
            sb.append("&so_tien=").append(amount);
        }
        if (orderCode != null && !orderCode.isBlank()) {
            sb.append("&noi_dung=").append(enc(orderCode));
        }
        if (!bareQr && accountHolder != null && !accountHolder.isBlank()) {
            sb.append("&chu_tai_khoan=").append(enc(accountHolder));
        }
        return sb.toString();
    }

    private static String enc(String s) {
        if (s == null) return "";
        return URLEncoder.encode(s, StandardCharsets.UTF_8);
    }
}
