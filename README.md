Delivery estimates (1.0.7)
==========================

Warehouse edit forms now accept optional `delivery_days_min` and
`delivery_days_max` (whole business days, 0-365). Set both or neither; minimum
must not exceed maximum. These are the TOTAL time until customer delivery,
including supplier lead time and transit, not just the supplier dispatch time.
No existing warehouse lead times are populated automatically.

The same optional product attributes appear in the admin product editor's
**Inventory > Delivery estimate** section for simple products in all attribute
sets, with store-view inheritance. The native attribute form is moved from its
dedicated tab without changing attribute assignments or stored values. If an
administrator adds unrelated visible attributes to that attribute group, its
original tab is preserved instead of moving unrelated fields. They apply
only when the product has NO Multiwarehouse rows. Existing rows with zero or
insufficient quantity, or missing warehouse intervals, never fall back to a
product interval. Core stock availability still applies. Backorders alone do
not create a delivery promise.

Allocation follows warehouse `position`, then ID. If several warehouses are
needed for the requested quantity, use the slowest minimum and maximum among
the allocated warehouses (the complete quantity must arrive). The feature does
not change stock deduction, checkout, order splitting or warehouse priority.
Configurable products require a valid, complete selection and use the selected
simple child's data. Unselected/invalid variants and unsupported product types
(bundle, grouped, virtual, downloadable) do not receive an invented date.

Enable under **System > Configuration > Catalog > Inventory > Multiwarehouse Delivery
Estimate** at the narrowest needed store scope. It defaults to OFF.
The fields retain their `catalog/wgmulti_delivery/*` storage paths so moving
the admin group does not discard existing scoped settings.
The optional daily cutoff uses the store timezone. Saturday/Sunday and explicitly configured
`YYYY-MM-DD` exclusions are skipped; there is no built-in national holiday or
special working-Saturday feed. Maintain exclusions for the delivery territory.
The starting day is the current business day, or the next business day after
cutoff/on a non-business day. Zero days means that starting day; positive days
are counted after it. The default UI disclaimer identifies domestic home
delivery and notes that advance payment can shift the estimate.

The product-page block is added to `product.info.extrahint`. A read-only POST
to `wgmulti/delivery/estimate` returns current dates with private/no-store
headers and an Amasty FPC exclusion. The cacheable HTML contains only product
ID, store URL and an empty placeholder, never dates, quantities or session IDs.
Quantity and configurable changes refresh the estimate, including theme
controls that do not emit native events. Stale in-flight responses are ignored.
Visible open tabs refresh at cutoff/midnight or within five minutes. An unknown
estimate or request failure hides the block. The calculation is a quote, not a
guaranteed delivery date. LMS styling is owned by `lms/design`: its layout moves
the block to the first child position under `product.info.addtocart`, directly
below the cart button and ahead of payment calculators. Its compact template
omits the optional disclaimer; the JS supports templates without that element.
Its slot reserves compact space while a fresh estimate is loading, including
quantity/variant changes. Unavailable estimates and request failures collapse
the slot; a subsequent successful estimate restores it. The reserved HTML
contains no cached date or customer data.

Deployment: back up the database, deploy the package and matching theme, clean
config/layout/translate caches, then apply only this module's pending setup:

```bash
php8.3 public/shell/multiwarehouse-delivery.php --install
```

The installer validates the two warehouse columns and product attributes and
does not enable the feature or fill any intervals. Clean config/layout/translate
caches again, set reviewed lead times and calendar, and enable for the desired
store. No full reindex or full cache flush is required. Existing FPC pages may
need targeted product-tag invalidation to obtain the new placeholder/assets;
the live estimate itself is never stored in FPC. The modman map includes all
new JS/template/translation/CLI files.

Checks (run from the project root):

```bash
php8.3 vendor/webgriffe/multiwarehouse/tests/delivery.php
php8.3 vendor/webgriffe/multiwarehouse/tests/delivery-integration.php
php8.3 vendor/webgriffe/multiwarehouse/tests/delivery-admin.php
```

The admin rendering test checks native Inventory integration, locked fields,
store inheritance, unchanged values and non-duplicated form fields without
database writes. Its temporary HTML fixture is `/tmp/wgmulti-delivery-admin-fields.html`.
The integration test is dev-host restricted and rolls back its data. For actual
browser checks, create marked dev fixtures, run the two-context desktop/mobile
test, then ALWAYS remove the fixtures, including after a browser-test failure:

```bash
php8.3 vendor/webgriffe/multiwarehouse/tests/delivery-integration.php --prepare-browser
node vendor/webgriffe/multiwarehouse/tests/delivery-browser.mjs
php8.3 vendor/webgriffe/multiwarehouse/tests/delivery-integration.php --cleanup-browser
```

Update 1.0.2
Label usage example:
<?php echo $this->getlayout()->createBlock('wgmulti/catalog_product_warehouse')
    ->setProduct($_product)
    ->setTemplate('webgriffe/multiwarehouse/label.phtml')
    ->toHtml();
?>

When rendering a product collection, pass the loaded collection to the shared
label block. Warehouse quantities are then loaded in one query for the whole
collection instead of one query per product and warehouse:

<?php
$warehouseLabelBlock = $this->getLayout()
    ->createBlock('wgmulti/catalog_product_warehouse')
    ->setProductCollection($_productCollection)
    ->setTemplate('webgriffe/multiwarehouse/label.phtml');

foreach ($_productCollection as $_product) {
    echo $warehouseLabelBlock->setProduct($_product)->toHtml();
}
?>

Webgriffe_Multiwarehouse
========================
This Magento module was developed as proof of concept during the Mageday::2014 Workshop.

The module extends the "single warehouse" limitation of Magento through the
creation of a custom warehouse entity.

Facts
-----
- version 0.1.1
- extension key: Webgriffe_Multiwarehouse
- [extension on GitHub](https://github.com/webgriffe/Webgriffe_Multiwarehouse)

Release Notes
-------------
- 0.1.4 - Fixes some issues
- 0.1.3 - Fixes some issues
- 0.1.2 - Fixes some issues
- 0.1.1 - Proof of concept

Description
-----------


Compatibility
-------------
- Magento >= 1.8
- Not tested with previous versions; if you do and it works, please let us know in order to update the compatibility declaration. Thank you in advance.

Installation
------------
This extension can be installed through Modman.

For further information about Modman see https://github.com/colinmollenhour/modman/wiki/Tutorial

Uninstallation
--------------
This extension alters your DB schema by creating the following additional tables:

* <prefix>wg_warehouse
* <prefix>wg_warehouse_product

To uninstall this extension, manually drop the above tables and remove the
following from your Magento installation directory:

* app/code/community/Webgriffe/Multiwarehouse folder
* app/etc/modules/Webgriffe_Multiwarehouse.xml file
* app/design/adminhtml/default/default/layout/webgriffe/multiwarehouse/layout.xml file
* app/design/adminhtml/default/default/template/webgriffe/multiwarehouse/qty.phtml
* app/design/adminhtml/default/default/template/webgriffe/multiwarehouse/order.phtml
* skin/adminhtml/default/default/images/webgriffe/multiwarehouse/warehouse_icon.png
* skin/adminhtml/default/default/webgriffe_multiwarehouse.css
* var/log/wgmulti.log file (if any)

Contribution
------------
Any contribution is highly appreciated. The best way to contribute code is to [open a pull request on GitHub](https://help.github.com/articles/using-pull-requests).

Developers
----------
Alessandro Ronchi

- [http://www.alessandroronchi.com](http://www.alessandroronchi.com)
- [@aleron75](https://twitter.com/aleron75)
- [https://github.com/aleron75](https://github.com/aleron75)
- [+AlessandroRonchi](https://plus.google.com/+AlessandroRonchi)

Roberto Gambuzzi

- [http://www.gambuzzi.it](http://www.gambuzzi.it)
- [@gbinside](https://twitter.com/gbinside)
- [https://github.com/gbinside](https://github.com/gbinside)

Licence
-------
[OSL - Open Software Licence 3.0](http://opensource.org/licenses/osl-3.0.php)

Copyright
---------
(c) 2014+ Webgriffe
