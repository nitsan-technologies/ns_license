# Buy / Purchase QA checklist (ns_license Get New License)

Use after seeding `ns_product_details.checkout_url` and deploying the Buy UI.

## Catalog
- [ ] Migration applied: `composer/API/migrations/ns_product_details_checkout_url.sql`
- [ ] Migration applied: `composer/API/migrations/ns_product_details_checkout_redirect_param.sql`
- [ ] Pilot products have plan ids only (e.g. `69e33e613d0c724d2b06ef87`)
- [ ] Each product has its own `checkout_redirect_param` (e.g. `cf_redirectto_xxxxx` from Pabbly)
- [ ] Signed `GetProduct.php` returns non-empty `checkoutUrl` + `checkoutRedirectParam` and `priceAnnual`

## Pabbly checkout UI (hide RedirectTo / URL field)
The return URL is passed as `cf_redirectto_*` and Pabbly may render it as a visible “URL” input under Basic Information. TYPO3 cannot hide a cross-origin checkout field — configure Pabbly:

1. Pabbly Subscription Billing → Product → plan → **Checkout Customizer**
2. Open the **Custom Fields** block that contains **RedirectTo** / URL (`cf_redirectto_…`)
3. Prefer one of:
   - Remove that custom field from the checkout form **only if** post-pay return still works via your thank-you / ViewHelper flow, **or**
   - Keep the field for redirect prefill but move it out of the visible Basic Information section if your Pabbly plan allows (field type list has no native “Hidden”)
4. Re-test: checkout must not show the TYPO3 return URL; after payment the browser must still reach `purchase_success=1` (and optional `purchase_token`)

## Backend Buy flow
- [ ] Product without `checkout_url`: Buy radio disabled + hint shown
- [ ] Product with checkout: Buy enabled → purchase step shows name + annual price
- [ ] Terms required before “Proceed to payment”
- [ ] `prepare_checkout` AJAX returns allowlisted URL including RedirectTo query param
- [ ] Payment closes Get New License and opens a **single** TYPO3 checkout modal (iframe); “Open in new tab” works
- [ ] No nested checkout inside Get New License; no “I completed payment” button
- [ ] CSP does not block `t3planet.shop` / `payments.pabbly.com` iframe

## Post-payment (Pabbly → NewOrderCreateLicense + RedirectTo)
- [ ] After pay, browser returns to license module with `purchase_success=1`
- [ ] Flash message: check email for license key / Activate
- [ ] Webhook hits `composer/API/NewOrderCreateLicense.php` with `order` + `products`
- [ ] `ns_product_license` row created; customer email contains license key
- [ ] Key activates in Admin Tools → T3Planet License

## Regression
- [ ] Free Trial + OTP still works end-to-end
- [ ] Module access remains systemMaintainer-only
