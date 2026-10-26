# Third-party notices and provenance

This demo preserves application work developed around Laravel scaffolding and an admin-template layout. It is not a claim of authorship over upstream components.

- **Laravel framework/scaffolding**, Laravel UI, Sanctum and related Composer dependencies: their own package licenses, available in installed package distributions and `composer.lock`.
- **ElaAdmin / Colorlib**: original admin-template influence. Colorlib is credited as the original template author. Original template README identified MIT licensing and required author attribution. Original template dashboard, sample photos, copied map API code and business artwork are excluded from this demo.
- **Bootstrap**: MIT; upstream notice retained in npm distribution and generated browser-asset notices.
- **jQuery**, **Toastr**, and **DataTables / DataTables Bootstrap 5**: upstream distributions and license notices are copied by `tools/prepare-assets.cjs` where supplied.
- **Font Awesome Free 6**: icons CC BY 4.0, fonts SIL OFL 1.1, code MIT. Original upstream notices remain in the npm CSS/font package; its license is copied with generated browser assets.
- Other Composer/npm packages retain the licenses identified in their lockfiles/distributions.

`npm run build` places available package license files under `public/assets/vendor/licenses/`. Generated dependencies are not committed; reproduce them from the lockfile. Preserve attribution and package licenses when redistributing builds.

No new blanket license is granted by this demo over the application's code, third-party work or marks. Business names/logos, user photos and operational documents from the source installation are excluded.
