package vn.npay.ezyplatform;

import org.springframework.boot.context.properties.ConfigurationProperties;
import org.springframework.stereotype.Component;

/**
 * Cấu hình NPay đọc từ application.properties (prefix "npay").
 *
 * Ví dụ:
 * <pre>
 * npay.api-token=xxxxx
 * npay.account-number=0123456789
 * npay.bank-bin=970422
 * npay.account-holder=CONG TY NPAY
 * npay.qr-template=compact
 * npay.api-base-url=https://api.npay.vn
 * npay.qr-base-url=https://qr.npay.vn
 * npay.merchant-portal=https://my.npay.vn
 * </pre>
 */
@Component
@ConfigurationProperties(prefix = "npay")
public class NPayConfig {

    /** Token xác thực webhook (header Authorization: Apikey &lt;token&gt;). */
    private String apiToken;

    /** Số tài khoản nhận tiền. */
    private String accountNumber;

    /** Mã BIN ngân hàng (vd 970422 = MB Bank). */
    private String bankBin;

    /** Tên chủ tài khoản hiển thị. */
    private String accountHolder;

    /** Template QR: compact | compact2 | qr_only | print. */
    private String qrTemplate = "compact";

    /** Endpoint API NPay. */
    private String apiBaseUrl = "https://api.npay.vn";

    /** Endpoint sinh ảnh QR. */
    private String qrBaseUrl = "https://qr.npay.vn";

    /** Cổng quản trị merchant. */
    private String merchantPortal = "https://my.npay.vn";

    public String getApiToken() { return apiToken; }
    public void setApiToken(String apiToken) { this.apiToken = apiToken; }

    public String getAccountNumber() { return accountNumber; }
    public void setAccountNumber(String accountNumber) { this.accountNumber = accountNumber; }

    public String getBankBin() { return bankBin; }
    public void setBankBin(String bankBin) { this.bankBin = bankBin; }

    public String getAccountHolder() { return accountHolder; }
    public void setAccountHolder(String accountHolder) { this.accountHolder = accountHolder; }

    public String getQrTemplate() { return qrTemplate; }
    public void setQrTemplate(String qrTemplate) { this.qrTemplate = qrTemplate; }

    public String getApiBaseUrl() { return apiBaseUrl; }
    public void setApiBaseUrl(String apiBaseUrl) { this.apiBaseUrl = apiBaseUrl; }

    public String getQrBaseUrl() { return qrBaseUrl; }
    public void setQrBaseUrl(String qrBaseUrl) { this.qrBaseUrl = qrBaseUrl; }

    public String getMerchantPortal() { return merchantPortal; }
    public void setMerchantPortal(String merchantPortal) { this.merchantPortal = merchantPortal; }
}
