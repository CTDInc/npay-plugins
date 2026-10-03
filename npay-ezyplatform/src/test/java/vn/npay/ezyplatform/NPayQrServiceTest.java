package vn.npay.ezyplatform;

import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.*;

class NPayQrServiceTest {

    @Test
    void buildQrUrl_minimal() {
        String url = NPayQrService.buildQrUrl(
                "https://qr.npay.vn",
                "0123456789",
                "970422",
                100000L,
                "NPAY42",
                null,
                null
        );
        assertTrue(url.startsWith("https://qr.npay.vn/qrcard?"), "URL phải bắt đầu bằng qr.npay.vn/qrcard?");
        assertTrue(url.contains("ma_bin=970422"));
        assertTrue(url.contains("tai_khoan=0123456789"));
        assertTrue(url.contains("so_tien=100000"));
        assertTrue(url.contains("noi_dung=NPAY42"));
    }

    @Test
    void buildQrUrl_withHolderAndTemplate() {
        String url = NPayQrService.buildQrUrl(
                "https://qr.npay.vn/",
                "0123456789",
                "970422",
                50000L,
                "ORDER-1",
                "CONG TY NPAY",
                "compact"
        );
        // baseUrl trailing slash phải được trim
        assertTrue(url.startsWith("https://qr.npay.vn/qrcard?"));
        // space được encode thành '+' bởi URLEncoder
        assertTrue(url.contains("chu_tai_khoan=CONG+TY+NPAY"));
        assertTrue(url.contains("noi_dung=ORDER-1"));
    }

    @Test
    void buildQrUrl_zeroAmount_omitsAmount() {
        String url = NPayQrService.buildQrUrl(
                "https://qr.npay.vn",
                "0123456789",
                "970422",
                0L,
                "X",
                null,
                null
        );
        assertFalse(url.contains("so_tien="), "amount=0 thì không append");
    }

    @Test
    void buildQrUrl_blankBaseUrl_fallsBack() {
        String url = NPayQrService.buildQrUrl(
                "",
                "0123456789",
                "970422",
                1000L,
                "A",
                null,
                null
        );
        assertTrue(url.startsWith("https://qr.npay.vn/qrcard?"));
    }

    @Test
    void buildQrUrl_bankCodeAndBareQr() {
        String url = NPayQrService.buildQrUrl(
                "https://qr.npay.vn/img",
                "0123456789",
                "VCB",
                1000L,
                "A",
                "CONG TY NPAY",
                "qr_only"
        );
        assertTrue(url.startsWith("https://qr.npay.vn/qrpay?"));
        assertTrue(url.contains("ngan_hang=vietcombank"));
        assertFalse(url.contains("chu_tai_khoan"));
    }

    @Test
    void hmacSha256Hex_matchesKnownVector() {
        // RFC 4231 test case 2
        assertEquals("5bdcc146bf60754e6a042426089575c75a003f089d2739839dec58b964ec3843",
                NPayWebhookController.hmacSha256Hex("Jefe", "what do ya want for nothing?"));
    }

    @Test
    void payload_acceptsStringId() throws Exception {
        NPayWebhookPayload p = new com.fasterxml.jackson.databind.ObjectMapper().readValue(
                "{\"id\":\"tx_8f3k2m9q\",\"transferType\":\"in\",\"transferAmount\":1000,\"referenceCode\":null}",
                NPayWebhookPayload.class);
        assertEquals("tx_8f3k2m9q", p.getId());
        assertNull(p.getReferenceCode());
    }

    @Test
    void extractOrderCode_findsPattern() {
        assertEquals("NPAY12345",
                NPayWebhookController.extractOrderCode("Chuyen khoan NPAY12345 cam on"));
        assertEquals("ORDER42",
                NPayWebhookController.extractOrderCode("thanh toan order42"));
        assertNull(NPayWebhookController.extractOrderCode("noi dung khong co ma"));
        assertNull(NPayWebhookController.extractOrderCode(null));
    }
}
