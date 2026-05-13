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
        assertTrue(url.startsWith("https://qr.npay.vn/img?"), "URL phải bắt đầu bằng qr.npay.vn/img?");
        assertTrue(url.contains("acc=0123456789"));
        assertTrue(url.contains("bank=970422"));
        assertTrue(url.contains("amount=100000"));
        assertTrue(url.contains("des=NPAY42"));
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
        assertFalse(url.contains("//img"));
        assertTrue(url.contains("template=compact"));
        // space được encode thành '+' bởi URLEncoder
        assertTrue(url.contains("accountName=CONG+TY+NPAY"));
        assertTrue(url.contains("des=ORDER-1"));
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
        assertFalse(url.contains("amount="), "amount=0 thì không append");
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
        assertTrue(url.startsWith("https://qr.npay.vn/img?"));
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
