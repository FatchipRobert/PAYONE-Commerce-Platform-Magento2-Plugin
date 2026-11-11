# Pay in Store („Bezahlung bei Abholung“) – Arbeitsstand 11.11.2026

Übergabe-Notiz für die Weiterarbeit (ehemals „Click & Collect“). Technische Kurzbeschreibung steht in `CLAUDE.md` (Abschnitt „Pay in Store“), offene Punkte zusätzlich in `TODO.md`.

## Anforderungen (vom Kunden / PAYONE)

- Name „Pay in Store“, im Frontend nur „Bezahlung bei Abholung“ ohne weitere Informationen.
- Technisch nur Commerce Case + Checkout anlegen (Kunde + Warenkorb), **keine Order** (= kein Payment-Request).
- Ein Cronjob prüft den Checkout-Status; bezahlt → Bestellung als bezahlt markieren und weiterverarbeiten.
- Einstellung: Bestellungen nach X Tagen ohne Zahlungseingang stornieren – pro Store View/Website, ab Bestelldatum.
- Checkout-Status laut PAYONE: `statusOutput.paymentStatus` (`WAITING_FOR_PAYMENT`, …, dazu `openAmount`, `collectedAmount`, `cancelledAmount`, `refundedAmount`, `chargebackAmount`).

## Umgesetzt (Grundgerüst, Modul-Code noch nicht committet)

- Zahlart `payone_cp_payinstore` (`Payone::METHOD_PAY_IN_STORE`, in `Payone::METHODS`), Offline-Zahlart:
  - `etc/config.xml`: `can_initialize` 1, alle anderen `can_*` 0 (Rechnung/Storno/Gutschrift nur offline), `order_status` pending, `is_gateway` 0, `cancel_after_days` 0, Titel „Bezahlung bei Abholung“, standardmäßig inaktiv.
  - `etc/di.xml`: Facade mit eigenem `PayoneCPPayInStoreCommandPool` (nur `initialize`) → `PayoneCPPayInStoreInitializeCommand`: Builder `context` + `commerce_case` + `PayoneCPCheckoutWithoutOrderBuilder` (`CheckoutBuilder` mit neuem Parameter `autoExecuteOrder` = false), TransferFactory `POST /commerce-cases` mit Protokollname `CreateCommerceCase`, Handler `Gateway/Response/CheckoutIdsHandler` (speichert Commerce-Case- und Checkout-ID).
  - Bestellung bleibt `new`/`pending`, Bestellmail geht normal raus.
- `Cron/CheckPayInStoreOrders` (`etc/crontab.xml`, alle 15 Min.): alle Bestellungen mit State `new`, Checkout-ID und Zahlart Pay in Store → `Model/PayInStore/OrderStatusUpdater::update()`; Fehler einer Bestellung werden geloggt, die übrigen laufen weiter.
- `OrderStatusUpdater`: `get_checkout_details` → `statusOutput.paymentStatus`
  - `PAYMENT_COMPLETED` → `registerCaptureNotification()` (Rechnung, Transaktions-ID `<checkoutId>-instore`), Rechnungsmail.
  - `WAITING_FOR_PAYMENT` + älter als `cancel_after_days` (0 = nie) → `$order->cancel()` + Verlaufskommentar. Storniert wird nur in Magento, nicht bei PAYONE.
  - `PAYMENT_NOT_COMPLETED` (Teilzahlung?) und alles andere → nichts tun.
  - `PAYMENT_COMPLETED` stammt aus alten API-Logs des Dev-Servers (Checkouts anderer Zahlarten) und ist von PAYONE noch nicht für Pay in Store bestätigt.
- Admin: `etc/adminhtml/system/payone_cp_payinstore.xml` (Aktiv, Titel, „Cancel Unpaid Orders After (Days)“, Min/Max, Sortierung), in `system.xml` eingebunden.
- Frontend: `method-renderer/payinstore-cp-method.js` + `template/payment/payinstore.html` (normaler „Bestellen“-Button, keine Hinweise), registriert in `payone-cp-payments.js`, `checkout_index_index.xml` (`isBillingAddressRequired`).
- `etc/payment.xml`, `i18n/de_DE.csv` (4 neue Texte), `CLAUDE.md`, `TODO.md` aktualisiert.

## Tests

- Unit-Tests: `Test/Unit/Model/PayInStore/OrderStatusUpdaterTest`, `Test/Unit/Cron/CheckPayInStoreOrdersTest`, erweitert: `CheckoutBuilderTest`, `ResponseHandlersTest`. 363 Tests grün, 100 % Line Coverage, phpcs sauber.
- E2E (`magento_pocommerce249-e2e`, committet + gepusht, Commit `80b1109`): `tests/37-payinstore.spec.js` – Request ohne Order, Bestellung pending, Cron lässt sie innerhalb der Frist offen und storniert sie danach (Bestelldatum per SQL zurückgesetzt). Neue Helfer in `lib/server.js`: `runMagento()`, `runCronJob()`, `setOrderCreatedDaysAgo()`; `lib/payment.js`: `payInStore()`. Komplette Suite: 34 bestanden, 7 übersprungen.
- **Nicht getestet:** der bezahlte Fall (`PAYMENT_COMPLETED`), da eine Zahlung im Laden nicht simulierbar ist.

## Zustand Dev-Server

- `payment/payone_cp_payinstore/active` = 1 (per `config:set`), `cancel_after_days` = 0 (vom E2E-Test zurückgesetzt).
- `setup:di:compile` mit den neuen virtualTypes ist gelaufen.
- Testbestellung `000000493`: Pay in Store, vom Cron storniert.

## Offene Fragen (an Händler bzw. PAYONE gestellt)

1. Händler: Teilzahlung/abweichender Betrag im Laden möglich? (aktuell: Rechnung nur bei vollständiger Zahlung)
2. Händler: Bei Stornierung auch den PAYONE-Checkout stornieren? (angenommen ja) → PAYONE: Mit welchem Aufruf storniert man einen Checkout ohne Order, welcher Status folgt?
3. Händler: Erstattung nur als Offline-Gutschrift? (aktuell so)
4. Händler: Zahlart nur bei Abhol-Versandart anbieten, und wenn ja, bei welcher? (aktuell immer verfügbar)
5. PAYONE: Ist `PAYMENT_COMPLETED` der Status nach vollständiger Zahlung im Laden? Ist `collectedAmount` der verlässliche Betrag? Gibt es Webhooks dazu, mit denen sofort statt per Cron reagiert werden könnte?

## Antworten des Kunden (10.11.2026, Kurzfassung)

1. Nur Commerce Case + Checkout; beim Backsync nur den Checkout synchronisieren.
2. Teilzahlung/abweichender Betrag kommt vor (Kunde nimmt an der Kasse ein Produkt heraus) → Checkout muss aktuell gehalten werden.
3. Bei Storno in Magento Checkout stornieren, Commerce Case bleibt stehen.
4. Erstattung an der Kasse, in Magento reicht eine Offline-Gutschrift.
5. Nur bei Versandart „In-Store Pickup“ (`instore_pickup`) anbieten.
6. `PAYMENT_COMPLETED` und `PAYMENT_NOT_COMPLETED` (Teilabholung) müssen möglich sein.
7. Storno im Laden → Checkout weg → in Magento stornieren; Storno in Magento → `PATCH`/`DELETE`.
8. Kein Testmodus, „müssen wir einmal durchspielen“.

## API-Versuch Pre-Prod (10.11.2026)

Signierte Aufrufe per Skript `~/pcp_probe/call.php` auf dem Dev-Server (`php call.php METHOD /commerce-cases/... [json]`), Testbestellungen 000000507–510 (je 2 Artikel, 72,10 €). Eine Zahlung im Laden lässt sich über **Payment Information** simulieren: `POST …/checkouts/{id}/payment-information` mit `amountOfMoney`, `type`, `paymentChannel` `POS`, `paymentProductId` 6000 (Cash), `merchantReference`.

| Aktion | Ergebnis am Checkout |
|---|---|
| Neu angelegt | `checkoutStatus` `OPEN`, `paymentStatus` `WAITING_FOR_PAYMENT`, `isModifiable` true, `openAmount` 0 |
| `SALE` 34,00 von 72,10 € (507) | `BILLED`, **`PAYMENT_COMPLETED`**, `collectedAmount` 3400, `openAmount` 0, `isModifiable` **false** – also schon bei Teilzahlung „completed“ |
| zweiter `SALE` über den Rest (507) | `collectedAmount` 7210, sonst unverändert |
| `PATCH` mit Positionen ohne `id` (508) | 400 „checkout amount and amount of the shoppingCart do not match“ – Positionen werden angehängt, nicht ersetzt |
| `PATCH` mit `orderLineDetails.id` + `quantity` 0 + neuem `amountOfMoney` (508) | 204, Position entfernt, Betrag 34,02 € |
| `POST …/cancel` mit `PARTIAL`/`cancelItems` (508) | 400: Positionen nicht im Status `ORDERED` (nur mit Order) |
| `POST …/payment-information/{id}/refund` | 400 „initial transaction processed outside of current system“ |
| neue Payment Information `type` `REFUND` 10,00 € (508) | `refundedAmount` 1000, `collectedAmount` 3402 → 2402, weiter `PAYMENT_COMPLETED` |
| `DELETE` offener Checkout (509) | 204, danach `GET` 200 mit `checkoutStatus` `DELETED`, `paymentStatus` `NO_PAYMENT` (kein 404); im Commerce Case weiter gelistet |
| `DELETE` bezahlter Checkout (508) | 409 „not in the status OPEN“ |
| `RESERVATION` 34,00 € (510) | `COMPLETED`, **`PAYMENT_NOT_COMPLETED`**, `openAmount` 3400 |
| danach `CAPTURE` 34,00 € als neue Payment Information (510) | `BILLED`, weiter `PAYMENT_NOT_COMPLETED`, `openAmount` 3400, `collectedAmount` 3400 |

Erkenntnisse:
- `paymentStatus` sagt nichts über den vollständigen Betrag aus – maßgeblich ist `collectedAmount` (abzüglich Erstattungen) im Vergleich zum Checkout-Betrag. Der aktuelle Code würde 507/508 bei `PAYMENT_COMPLETED` voll in Rechnung stellen (508: 72,10 € statt 24,02 €).
- Positionen bekommen durch Payment Information keinen Status – Teilrechnungen lassen sich nur über Betrag oder über einen vorher per `PATCH` angepassten Warenkorb ableiten.
- Nach der ersten Zahlung ist der Checkout nicht mehr änderbar (`isModifiable` false) und nicht mehr löschbar.
- Storno im Laden = `checkoutStatus` `DELETED` (nicht 404).
- Wie die echte Kasse (POS) bucht – `SALE` oder `RESERVATION` + `CAPTURE`, ob sie Positionen per `PATCH` entfernt –, ist offen und mit PAYONE/Händler zu klären.

## Antworten PAYONE (10.11.2026)

- Die Kasse bucht immer `SALE` (kein `RESERVATION` + `CAPTURE`). Eine fertige Kassen-Anbindung gibt es noch nicht, Details stehen bei PAYONE noch nicht fest.
- `paymentProductId`: 6000 bar, 199 girocard (Erstattung girocard: 772), Kreditkarte dieselben Ids wie im E-Commerce.
- Die Fragen zu Teil-Status (Teilzahlung/Teilabholung, Bedeutung von `PAYMENT_COMPLETED`, Status pro Position, Webhooks, Testmöglichkeit) sind unbeantwortet – das Thema entsteht bei PAYONE erst noch, vorerst ist nicht mehr zu erwarten.

Folgerungen:
- Der Fall `RESERVATION` (`PAYMENT_NOT_COMPLETED` mit `openAmount`) kommt von der Kasse nicht vor.
- Bei `SALE` meldet PAYONE schon nach der ersten Teilzahlung `PAYMENT_COMPLETED` (siehe API-Versuch) – maßgeblich bleibt `collectedAmount` (nach Erstattungen bereits verringert).
- Nach der ersten Zahlung ist der Checkout nicht mehr änderbar; eine Teilabholung kann also nur als weiterer `SALE` kommen, Positionen werden danach nicht mehr entfernt.
- Die Produkt-Ids sind für den Sync egal (Magento wertet nur Beträge aus); für die E2E-Simulation reicht 6000.

## Umgesetzt am 10.11.2026

- **Nur bei In-Store Pickup**: `Observer/PayInStore/ShippingMethodAvailability` (`payment_method_is_active`) – Zahlart nur bei Versandart `instore_pickup` (greift auch beim Setzen der Zahlart am Quote).
- **Storno löscht den Checkout**: `Observer/PayInStore/DeleteCheckout` (`sales_order_payment_cancel`) → Service-Command `delete_checkout` (`DELETE`, Protokollname `DeleteCheckout`). Gilt für Admin-Storno und Cron-Storno nach X Tagen. Ist der Checkout nicht mehr `OPEN` (Zahlung erfasst), lehnt PAYONE mit 409 ab und die Bestellung wird nicht storniert (Admin sieht die PAYONE-Meldung). `ResponseValidator` akzeptiert dafür HTTP 204 ohne Body.
- **Storno im Laden**: Cron erkennt `checkoutStatus` `DELETED` → Bestellung storniert (Kommentar „Checkout wurde im Laden storniert…“), ohne zweiten `DELETE` (Flag `DeleteCheckout::FLAG_CHECKOUT_DELETED` am Payment).
- Dev-Server: In-Store Pickup aktiv (`carriers/instore/active` = 1), Source `default` als Abholort „Testfiliale Berlin“ (DE, Berlin) per SQL eingerichtet.
- Tests: Unit-Tests ergänzt (378, 100 % Coverage, phpcs sauber). E2E `37-payinstore`: nicht angeboten bei Flat Rate, Bestellung per Store Pickup (Rechnungsadresse im Zahlungsschritt), Cron-Storno + `DeleteCheckout`, Admin-Storno + `DeleteCheckout`, Storno im Laden (`deleteCheckoutAtPayone()` in `lib/server.js`) → Cron storniert. `fillGuestShippingStep()` wählt jetzt explizit Flat Rate.

## Festlegung Händler (11.11.2026, verbindlich)

> DELETE: Stellen wir bei PO an der API einen Delete fest, canceln wir in Magento offline. Wird in Magento gecancelt, löschen wir den Checkout in PO (CommerceCase bleibt unberührt).
> PAYMENT: Stellen wir eine Zahlung fest – egal ob voll oder teilweise – setzen wir die Bestellung auf abgeschlossen und bezahlt.
> Darüber hinaus betrachten wir nichts weiter.

Umsetzung (abgestimmt mit dem User):
- Zahlung = `statusOutput.collectedAmount` > 0 → Rechnung über den vollen Bestellbetrag (auch bei Teilzahlung), Rechnungsmail. „Abgeschlossen“ = bezahlt/in Rechnung gestellt; **keine Lieferung** (das Modul legt nie Lieferungen an), die Bestellung steht danach auf `processing`.
- Storno in beide Richtungen wie am 10.11. umgesetzt; Cron-Storno nach X Tagen ohne Zahlung (`cancel_after_days`) bleibt.
- Nicht synchronisiert: Teilabholung, entfernte Positionen, Erstattungen im Laden (Gutschrift offline von Hand in Magento).
- Ein Admin-Storno zwischen Zahlung im Laden und nächstem Cron-Lauf (≤ 15 min) scheitert mit 409 von PAYONE – gewollt.
- Tests: Unit-Tests 379, 100 % Coverage. E2E: `payInStoreAtPayone()` in `lib/server.js` bucht Payment Information `SALE`/`POS`/6000 über den HTTP-Client des Moduls (Protokollname `PaymentInformation`); Teilzahlung 10,00 € → Rechnung über den vollen Betrag.

## Nächste Schritte

- Modul-Änderungen committen (auf Anfrage).
