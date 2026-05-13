<?php
/**
 * NPay Payment Gateway for HostBill
 *
 * Tích hợp cổng thanh toán NPay (npay.vn) vào HostBill.
 * Cho phép khách hàng thanh toán qua chuyển khoản ngân hàng kèm mã QR động;
 * NPay sẽ gửi webhook (IPN) về callback URL khi nhận được tiền và module
 * sẽ tự động cộng thanh toán vào hóa đơn tương ứng.
 *
 * @author  NPay Team
 * @version 1.0.0
 * @link    https://npay.vn
 */

if (!defined('HBROOTDIR')) {
    // Cho phép gọi trực tiếp từ /includes/modules/gateways/callback/npay.php
    // HostBill thường tự load các định nghĩa khi gateway được khởi tạo.
}

class npay extends HostBillPaymentGateway
{
    /**
     * Default configuration shown in HostBill admin
     * Settings → Payments → Modules → NPay.
     *
     * @var array
     */
    public $config = [
        'name'        => 'NPay',
        'description' => 'NPay - Cổng thanh toán chuyển khoản ngân hàng tự động (Việt Nam)',
        'config'      => [
            'api_token' => [
                'value' => '',
                'type'  => 'input',
                'label' => 'API Token (Apikey)',
                'description' => 'Token xác thực webhook do NPay cấp. Header: Authorization: Apikey &lt;token&gt;',
            ],
            'account_number' => [
                'value' => '',
                'type'  => 'input',
                'label' => 'Số tài khoản ngân hàng nhận tiền',
                'description' => 'Ví dụ: 113366668888',
            ],
            'bank_bin' => [
                'value' => '970415',
                'type'  => 'input',
                'label' => 'Mã BIN ngân hàng',
                'description' => 'Mã BIN ngân hàng theo chuẩn NAPAS (VD: 970415 = VietinBank, 970422 = MBBank).',
            ],
            'bank_short_name' => [
                'value' => 'VietinBank',
                'type'  => 'input',
                'label' => 'Tên viết tắt ngân hàng',
                'description' => 'Hiển thị cho khách (VD: VietinBank, MBBank, Vietcombank).',
            ],
            'qr_template' => [
                'value'   => 'compact',
                'type'    => 'select',
                'options' => [
                    'compact' => 'Compact (gọn)',
                    'qronly'  => 'QR Only (chỉ QR)',
                    'print'   => 'Print (đầy đủ in)',
                ],
                'label' => 'Kiểu mẫu QR',
                'description' => 'Chọn loại ảnh QR sinh từ qr.npay.vn.',
            ],
            'prefix_code' => [
                'value' => 'NPAY-',
                'type'  => 'input',
                'label' => 'Tiền tố mã giao dịch',
                'description' => 'Mã sẽ là &lt;prefix&gt;&lt;invoice_id&gt;, VD: NPAY-INV123.',
            ],
        ],
    ];

    /**
     * Module name shown in admin.
     */
    public function getName()
    {
        return 'NPay';
    }

    /**
     * Short description.
     */
    public function getDescription()
    {
        return 'NPay – Cổng thanh toán chuyển khoản ngân hàng tự động cho HostBill.';
    }

    /**
     * Long description / help text shown after activation.
     */
    public function getDescriptionFull()
    {
        return '<p><strong>NPay</strong> là cổng thanh toán chuyển khoản ngân hàng tự động dành cho thị trường Việt Nam. '
             . 'Khách hàng quét mã QR (VietQR) hoặc chuyển khoản với nội dung sinh tự động; '
             . 'NPay gửi webhook về HostBill để cộng tiền vào hóa đơn tức thì.</p>'
             . '<ul>'
             . '<li>Hỗ trợ tất cả các ngân hàng Việt Nam qua VietQR / NAPAS.</li>'
             . '<li>Tự đối soát giao dịch nhờ webhook ký bằng Apikey.</li>'
             . '<li>Tạo QR động qua <code>qr.npay.vn</code>.</li>'
             . '<li>Cấu hình webhook tại NPay dashboard trỏ về: '
             . '<code>https://your-hostbill.com/includes/modules/gateways/callback/npay.php</code></li>'
             . '</ul>';
    }

    /**
     * Called once when admin saves module settings.
     * Returns true on success.
     */
    public function setSettings()
    {
        // Validate cơ bản
        if (empty($this->config['config']['api_token']['value'])) {
            $this->addError('Vui lòng nhập API Token của NPay.');
            return false;
        }
        if (empty($this->config['config']['account_number']['value'])) {
            $this->addError('Vui lòng nhập số tài khoản ngân hàng.');
            return false;
        }
        if (empty($this->config['config']['bank_bin']['value'])) {
            $this->addError('Vui lòng nhập mã BIN ngân hàng.');
            return false;
        }
        return true;
    }

    /**
     * Build the unique transfer code for an invoice.
     */
    protected function buildTransferCode($invoiceId)
    {
        $prefix = isset($this->config['config']['prefix_code']['value'])
            ? $this->config['config']['prefix_code']['value']
            : 'NPAY-';
        return $prefix . 'INV' . (int) $invoiceId;
    }

    /**
     * Build the QR image URL from qr.npay.vn.
     */
    protected function buildQrUrl($amount, $code)
    {
        $acc      = urlencode($this->config['config']['account_number']['value']);
        $bin      = urlencode($this->config['config']['bank_bin']['value']);
        $template = urlencode($this->config['config']['qr_template']['value'] ?: 'compact');
        $amount   = (int) $amount;
        $code     = urlencode($code);
        return "https://qr.npay.vn/img?acc={$acc}&bank={$bin}&amount={$amount}&des={$code}&template={$template}";
    }

    /**
     * Render the payment form shown to the customer on the invoice page.
     *
     * @param object|array $invoice
     * @return string|array
     */
    public function drawForm($invoice = null)
    {
        // HostBill có thể truyền invoice qua đối số hoặc qua $this->invoice.
        if ($invoice === null && isset($this->invoice)) {
            $invoice = $this->invoice;
        }

        $invoiceId = 0;
        $amount    = 0;
        if (is_array($invoice)) {
            $invoiceId = isset($invoice['id']) ? $invoice['id'] : 0;
            $amount    = isset($invoice['total']) ? $invoice['total'] : (isset($invoice['amount']) ? $invoice['amount'] : 0);
        } elseif (is_object($invoice)) {
            $invoiceId = isset($invoice->id) ? $invoice->id : 0;
            $amount    = isset($invoice->total) ? $invoice->total : (isset($invoice->amount) ? $invoice->amount : 0);
        }

        $code   = $this->buildTransferCode($invoiceId);
        $qrUrl  = $this->buildQrUrl($amount, $code);

        $accountNumber = $this->config['config']['account_number']['value'];
        $bankShort     = $this->config['config']['bank_short_name']['value'];
        $bankBin       = $this->config['config']['bank_bin']['value'];

        // Nếu HostBill hỗ trợ Smarty template thì trả về mảng để engine render
        if (method_exists($this, 'getTemplate') || property_exists($this, 'smarty')) {
            $vars = [
                'qr_url'         => $qrUrl,
                'account_number' => $accountNumber,
                'bank_short'     => $bankShort,
                'bank_bin'       => $bankBin,
                'amount'         => $amount,
                'transfer_code'  => $code,
                'invoice_id'     => $invoiceId,
            ];
            // Một số bản HostBill assign trực tiếp:
            if (isset($this->smarty) && is_object($this->smarty)) {
                foreach ($vars as $k => $v) {
                    $this->smarty->assign($k, $v);
                }
                return ['template' => 'payment-form.tpl', 'vars' => $vars];
            }
            // fallback inline HTML
        }

        // Inline HTML fallback
        $html  = '<div class="npay-payment-box" style="border:1px solid #e3e3e3;padding:16px;border-radius:8px;max-width:480px;">';
        $html .= '<h3 style="margin-top:0;">Thanh toán qua NPay</h3>';
        $html .= '<p>Quét mã QR bên dưới hoặc chuyển khoản theo thông tin sau:</p>';
        $html .= '<div style="text-align:center;margin:12px 0;">';
        $html .= '<img src="' . htmlspecialchars($qrUrl) . '" alt="NPay QR" style="max-width:280px;width:100%;"/>';
        $html .= '</div>';
        $html .= '<table style="width:100%;font-size:14px;">';
        $html .= '<tr><td><strong>Ngân hàng</strong></td><td>' . htmlspecialchars($bankShort) . ' (BIN ' . htmlspecialchars($bankBin) . ')</td></tr>';
        $html .= '<tr><td><strong>Số tài khoản</strong></td><td>' . htmlspecialchars($accountNumber) . '</td></tr>';
        $html .= '<tr><td><strong>Số tiền</strong></td><td>' . number_format((float) $amount, 0, ',', '.') . ' VND</td></tr>';
        $html .= '<tr><td><strong>Nội dung CK</strong></td><td><code>' . htmlspecialchars($code) . '</code></td></tr>';
        $html .= '</table>';
        $html .= '<p style="margin-top:12px;color:#888;font-size:12px;">Hóa đơn sẽ được cập nhật tự động sau khi NPay xác nhận giao dịch.</p>';
        $html .= '</div>';
        return $html;
    }

    /**
     * IPN / Webhook handler.
     *
     * NPay POST JSON về URL:
     *   /includes/modules/gateways/callback/npay.php
     *
     * Header: Authorization: Apikey <token>
     * Body:
     * {
     *   "gateway":"VietinBank",
     *   "transactionDate":"2023-04-05 14:30:00",
     *   "accountNumber":"113366668888",
     *   "code":"NPAY-INV123",
     *   "content":"thanh toan don hang NPAY-INV123",
     *   "transferType":"in",
     *   "transferAmount":2277000,
     *   "accumulated":19077000,
     *   "referenceCode":"MBVCB.3278907687"
     * }
     */
    public function callback()
    {
        // 1) Xác thực token
        $expected = isset($this->config['config']['api_token']['value'])
            ? trim($this->config['config']['api_token']['value'])
            : '';

        $authHeader = $this->getAuthorizationHeader();
        $providedToken = '';
        if (preg_match('/^Apikey\s+(.+)$/i', $authHeader, $m)) {
            $providedToken = trim($m[1]);
        }

        if ($expected === '' || $providedToken === '' || !hash_equals($expected, $providedToken)) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }

        // 2) Đọc payload JSON
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
            return;
        }

        // 3) Chỉ xử lý giao dịch tiền vào
        $transferType = isset($payload['transferType']) ? strtolower($payload['transferType']) : '';
        if ($transferType !== 'in') {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Ignored non-incoming transfer']);
            return;
        }

        // 4) Trích invoice id từ field code (ưu tiên) hoặc content
        $code    = isset($payload['code']) ? (string) $payload['code'] : '';
        $content = isset($payload['content']) ? (string) $payload['content'] : '';
        $amount  = isset($payload['transferAmount']) ? (float) $payload['transferAmount'] : 0.0;
        $refCode = isset($payload['referenceCode']) ? (string) $payload['referenceCode'] : '';

        $invoiceId = $this->extractInvoiceId($code);
        if (!$invoiceId && $content) {
            $invoiceId = $this->extractInvoiceId($content);
        }

        if (!$invoiceId) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Cannot match invoice from code/content',
                'code'    => $code,
            ]);
            return;
        }

        // 5) Tránh ghi nhận trùng (idempotent) – HostBill có addInvoicePayment với transaction id
        try {
            $this->addInvoicePayment(
                $invoiceId,           // invoice id
                $refCode,             // transaction id (NPay reference)
                $amount,              // amount
                0,                    // fees
                'npay'                // gateway module name
            );
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            return;
        }

        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'success'    => true,
            'message'    => 'Payment recorded',
            'invoice_id' => $invoiceId,
            'amount'     => $amount,
            'reference'  => $refCode,
        ]);
    }

    /**
     * Pull "Authorization" header in a cross-server-compatible way.
     */
    protected function getAuthorizationHeader()
    {
        $header = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $headers = array_change_key_case($headers, CASE_LOWER);
            if (isset($headers['authorization'])) {
                $header = trim($headers['authorization']);
            }
        }
        return $header;
    }

    /**
     * Extract numeric invoice id from a transfer code/content.
     * Looks for "<prefix>INV<digits>" first, falls back to last digit cluster.
     */
    protected function extractInvoiceId($text)
    {
        if ($text === '' || $text === null) return 0;

        $prefix = isset($this->config['config']['prefix_code']['value'])
            ? $this->config['config']['prefix_code']['value']
            : 'NPAY-';
        $prefixQuoted = preg_quote($prefix, '/');

        if (preg_match('/' . $prefixQuoted . 'INV(\d+)/i', $text, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/INV(\d+)/i', $text, $m)) {
            return (int) $m[1];
        }
        // Last resort: pick the longest digit cluster
        if (preg_match_all('/\d+/', $text, $matches)) {
            $longest = '';
            foreach ($matches[0] as $d) {
                if (strlen($d) > strlen($longest)) $longest = $d;
            }
            if ($longest !== '') return (int) $longest;
        }
        return 0;
    }
}
