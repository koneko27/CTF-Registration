<?php
function generate_csp_nonce(): string {
	// Generate a unique nonce per request for better security
	return base64_encode(random_bytes(16));
}

function get_csp_header(string $nonce): string {
	return "default-src 'self'; " .
	       "script-src 'self' 'nonce-{$nonce}' https://cdnjs.cloudflare.com; " .
	       "style-src 'self' https://fonts.googleapis.com https://cdnjs.cloudflare.com; " .
	       "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
	       "img-src 'self' data: blob:; " .
	       "connect-src 'self'; " .
	       "frame-ancestors 'none'; " .
	       "base-uri 'self'; " .
	       "form-action 'self';";
}
