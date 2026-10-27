# Spec 23 — Accident Vehicles

**Status:** IMPLEMENTED 2026-10-22 (awaiting owner review; page switched OFF)
**Source:** owner request of 2026-10-22: "He wants to post cars that are accident cars too, so a dedicated page or a status showing all accident cars." Owner decisions the same day:

| Question | Decision |
|---|---|
| Where are they shown? | Their own page, kept apart. "Accident Vehicles" in the menu lists only accident cars, each labelled "Accident-damaged". They never appear in the normal Vehicles list. |
| How much damage information? | Full disclosure. Required: damage description, damaged areas, runs and drives, airbags deployed, Canadian title status, and at least one damage photo. |
| Legal | Build it and keep it hidden until the client confirms with the Ghana Revenue Authority or a customs broker that the cars may be imported (salvaged-vehicle restrictions, Act 1014). An admin switch controls this. |

## 1. Admin

* **Vehicle form** has a new "Accident damage" section. The box "This is an accident-damaged vehicle" shows these fields:
  * damage description (required, up to 3,000 characters);
  * damaged areas (required, one or more of: Front, Rear, Left side, Right side, Roof, Underbody, Interior, Flood / water);
  * Runs and drives (Yes / No / Not checked);
  * Airbags deployed (Yes / No / Not known);
  * Title status (Clean / Salvage / Rebuilt / Not known).

  For a normal vehicle, nothing is stored, whatever is posted.
* **Publish rule:** an accident vehicle can be published only when it has at least one photo of type **Damage** (new type on Manage Images, next to Exterior and Interior).
  * A new accident vehicle is therefore saved as not published first.
  * The website enforces the rule as well. An accident vehicle without a damage description or without a Damage photo is not shown, even if it is marked published.
  * The admin vehicle page warns about it ("has no Damage photo, so it cannot be shown").
* **Vehicle page and list:**
  * The admin vehicle page shows an "Accident damage" card.
  * The admin vehicle list marks the vehicle "Accident vehicle".
  * Creating one is audited as an accident vehicle, and changes to damage fields appear in the VEHICLE_UPDATED audit.
* **Settings** has an "Accident vehicles page" On/Off switch. It is a separate small form with CSRF, it needs the `settings.manage` permission, and it is audited as SETTINGS_UPDATED "switched ON/OFF". **The default is OFF.**

## 2. Website

* **Never with normal vehicles:**
  * Vehicles page, homepage latest vehicles, search options (makes, models, years, prices) and site assistant answers exclude accident vehicles, whether the switch is ON or OFF.
  * The section is fixed by the server; nothing in the URL can change it.
* **Switch OFF:**
  * `/accident-vehicles/` returns 404.
  * Accident vehicles' own pages return 404.
  * There is no menu or footer link.
  * Accident vehicles cannot be saved as favourites, and saved ones show as "No longer available".
* **Switch ON:**
  * **Menu:** "Accident Vehicles" in the menu (after Vehicles) and the footer.
  * **`/accident-vehicles/`:** same layout, filters, sort and pagination as Vehicles, with these differences:
    * its own heading;
    * a notice: "Every vehicle on this page has accident damage. Read the damage details and look at the damage photos ... before you reserve it";
    * each card carries an "ACCIDENT-DAMAGED" label;
    * its own empty state.
  * **Vehicle page of an accident car:**
    * "Back to Accident Vehicles" link;
    * an "Accident-damaged. Read the damage details before you reserve." flag under the price;
    * a "Damage" photo tab;
    * an "Accident damage" section above the specifications (description, damaged areas, runs and drives, airbags, title status).
  * Reserve, questions, WhatsApp and favourites work as for other vehicles.
* Nothing is invented. Only the values the administrator entered are shown, and a missing optional value is left out.

## 3. Data (migration 010)

* `vehicles` gets these columns:
  * `is_accident` (0/1, CHECK);
  * `damage_description` TEXT;
  * `damage_areas` SET(front, rear, left, right, roof, underbody, interior, flood);
  * `runs_drives` ENUM(yes, no, not_checked);
  * `airbags_deployed` ENUM(yes, no, unknown);
  * `title_status` ENUM(clean, salvage, rebuilt, unknown);
  * index `idx_vehicles_accident`.
* `vehicle_images.photo_type` gains `damage`.
* `settings.category` gains `features`. The key `accident_vehicles_enabled` holds the value "1"; no row means OFF.
* Backward compatible: existing vehicles are normal vehicles. Rollback steps are in the migration file. `schema.sql` is updated.

## 4. Tests

* **`accident_vehicles_test.php`: 40/40.** It covers:
  * validation (required disclosure, list values only, normal vehicles store nothing, old forms still work);
  * the publish rule (new vehicles, exterior photo not enough, Damage photo allows it);
  * auditing;
  * separation from Vehicles, homepage and search (also with a forged section filter);
  * switch OFF (page, detail and favourites unavailable) and switch ON (listing, label, filters, disclosure, favourites);
  * the website rule when the last Damage photo or the description is removed;
  * switch audit and default OFF.
* **`public_vehicles_test`:** cards now have the `accident` key.
* **Other suites:** all other suites pass, except the two known checks to be reworked in deployment preparation (`db_test` expects an empty database; `vehicle_image_test` counts real audit rows).
* **HTTP:** `/accident-vehicles/` is 404 while OFF and 200 while ON. Menu link only while ON.
* **Settings controller:** switch saved with flash, invalid value refused, missing CSRF refused.
* **Chrome (375 and 1366 px), using a temporary TEST vehicle that was removed afterwards: 16/16.**
  * Accident page: one labelled card, notice, current menu item.
  * Vehicles page without it.
  * Detail page: flag, Damage tab, damage section, back link.
  * No sideways scrolling and no console errors.

## 5. Before switching it ON (owner)

1. Confirm with the GRA or a customs broker which accident or salvage vehicles may be imported into Ghana (Act 1014 and its age and salvage rules).
2. Add the vehicles, with honest damage descriptions and Damage photos.
3. Admin, then Settings, then Accident vehicles page: On.
