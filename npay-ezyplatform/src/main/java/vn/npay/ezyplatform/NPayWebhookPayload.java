package vn.npay.ezyplatform;

import com.fasterxml.jackson.annotation.JsonIgnoreProperties;
import com.fasterxml.jackson.annotation.JsonProperty;

/**
 * Payload webhook giao dịch ngân hàng do NPay gửi đến.
 * Tham chiếu cấu trúc tương tự SePay/casso.
 */
@JsonIgnoreProperties(ignoreUnknown = true)
public class NPayWebhookPayload {

    /** ID giao dịch trên hệ thống NPay. */
    private Long id;

    /** Brand name của ngân hàng (vd "MB", "Vietcombank"). */
    private String gateway;

    /** Thời gian giao dịch (yyyy-MM-dd HH:mm:ss). */
    @JsonProperty("transactionDate")
    private String transactionDate;

    /** Số tài khoản thụ hưởng. */
    @JsonProperty("accountNumber")
    private String accountNumber;

    /** Mã đơn hàng tách từ nội dung CK (nếu NPay đã parse). */
    private String code;

    /** Nội dung chuyển khoản gốc. */
    private String content;

    /** "in" = tiền vào, "out" = tiền ra. */
    @JsonProperty("transferType")
    private String transferType;

    /** Số tiền giao dịch. */
    @JsonProperty("transferAmount")
    private long transferAmount;

    /** Số dư sau giao dịch. */
    private long accumulated;

    /** Số tài khoản đối ứng (người chuyển/nhận). */
    @JsonProperty("subAccount")
    private String subAccount;

    /** Mã tham chiếu duy nhất của ngân hàng. */
    @JsonProperty("referenceCode")
    private String referenceCode;

    /** Mô tả thêm. */
    private String description;

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }

    public String getGateway() { return gateway; }
    public void setGateway(String gateway) { this.gateway = gateway; }

    public String getTransactionDate() { return transactionDate; }
    public void setTransactionDate(String transactionDate) { this.transactionDate = transactionDate; }

    public String getAccountNumber() { return accountNumber; }
    public void setAccountNumber(String accountNumber) { this.accountNumber = accountNumber; }

    public String getCode() { return code; }
    public void setCode(String code) { this.code = code; }

    public String getContent() { return content; }
    public void setContent(String content) { this.content = content; }

    public String getTransferType() { return transferType; }
    public void setTransferType(String transferType) { this.transferType = transferType; }

    public long getTransferAmount() { return transferAmount; }
    public void setTransferAmount(long transferAmount) { this.transferAmount = transferAmount; }

    public long getAccumulated() { return accumulated; }
    public void setAccumulated(long accumulated) { this.accumulated = accumulated; }

    public String getSubAccount() { return subAccount; }
    public void setSubAccount(String subAccount) { this.subAccount = subAccount; }

    public String getReferenceCode() { return referenceCode; }
    public void setReferenceCode(String referenceCode) { this.referenceCode = referenceCode; }

    public String getDescription() { return description; }
    public void setDescription(String description) { this.description = description; }
}
