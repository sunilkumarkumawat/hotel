cacert.pem — the public root certificates that browsers and curl trust.

PHP on Windows ships without one. Unless php.ini has a curl.cainfo line
pointing somewhere, every HTTPS call made from PHP fails with

    SSL certificate problem: unable to get local issuer certificate

and that is the single most common reason a gateway which works perfectly from
a browser "stops working" from a XAMPP machine.

App\Support\WhatsApp uses this file when, and only when, php.ini has not been
told where the certificates are. A machine that has been configured keeps its
own setting. Certificate checking is never switched off: a connection that only
pretends to be secure is worse than one that fails honestly.

This is the standard Mozilla root store. To refresh it, download
https://curl.se/ca/cacert.pem over this file — nothing else needs changing.
