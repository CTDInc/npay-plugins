package vn.npay.ezyplatform;

/**
 * Interface dịch vụ đơn hàng — Ezyplatform host application phải cung cấp
 * implementation và đăng ký vào Spring context để webhook gọi được.
 */
public interface OrderService {

    /**
     * Đánh dấu đơn hàng đã thanh toán.
     *
     * @param orderCode mã đơn (chính là "code" trong payload webhook)
     * @param amount số tiền đã nhận
     * @param referenceCode mã tham chiếu giao dịch ngân hàng
     */
    void markPaid(String orderCode, long amount, String referenceCode);

    /**
     * Như {@link #markPaid(String, long, String)}, kèm id giao dịch NPay ("tx_…").
     * NPay có thể gửi lại cùng một giao dịch (retry) — host nên ghi nhận theo
     * {@code transactionId} một lần duy nhất (vd cột unique). Mặc định gọi bản 3 tham số.
     *
     * @param transactionId id công khai của giao dịch NPay, có thể null với payload cũ
     */
    default void markPaid(String orderCode, long amount, String referenceCode, String transactionId) {
        markPaid(orderCode, amount, referenceCode);
    }

    /**
     * (Tuỳ chọn) Lấy số tiền cần thanh toán của đơn. Trả &gt; 0 thì webhook bỏ qua
     * giao dịch chuyển thiếu; 0 = không kiểm.
     */
    default long getAmountDue(String orderCode) {
        return 0L;
    }
}
