## Layer Payment Payment Extension for CS-Cart 4.11

This extension utilizes Layer Payment API from Open and provides seamless integration with CS-Cart, allowing payments for Indian merchants via Credit Cards, Debit Cards, Net Banking (supports 3D Secure) without redirecting away from the CS-Cart site.

### Installation

Copy all files/folders recursively to CS-Cart root installation directory.

Open mysql or database and create entry for -

INSERT INTO `cscart_payment_processors` (`processor`, `processor_script`,
`processor_template`, `admin_template`, `callback`, `type`) 
VALUES ('layerpayment','layerpayment.php', 
'views/orders/components/payments/cc_outside.tpl','layerpayment.tpl', 'Y', 'P');

If re-installing then remove previous entry =
delete from cscart_payment_processors where `processor` = 'layerpayment'

Go to CS-Cart Admin Panel create a new payment method.
