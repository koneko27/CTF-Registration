<?php
function generate_csp_nonce(): string {
	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}
	if (empty($_SESSION['csp_nonce'])) {
		$_SESSION['csp_nonce'] = base64_encode(random_bytes(16));
	}
	return $_SESSION['csp_nonce'];
}

function get_csp_header(string $nonce): string {
	return "default-src 'self'; " .
	       "script-src 'self' 'nonce-{$nonce}' https://cdnjs.cloudflare.com; " .
	       "style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com https://cdnjs.cloudflare.com; " .
	       "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
	       "img-src 'self' data: blob:; " .
	       "connect-src 'self'; " .
	       "frame-ancestors 'none'; " .
	       "base-uri 'self'; " .
	       "form-action 'self';";
}
