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
     * (Tuỳ chọn) Lấy số tiền cần thanh toán của đơn.
     */
    default long getAmountDue(String orderCode) {
        return 0L;
    }
}
