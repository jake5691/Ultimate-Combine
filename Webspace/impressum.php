<?php
require_once __DIR__ . "/bootstrap.php";
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES, "UTF-8"); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars(t("impressum.title", "Ultimate Combine – Impressum"), ENT_QUOTES, "UTF-8"); ?></title>
  <link rel="icon" href="assets/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png">
  <link rel="manifest" href="assets/site.webmanifest">
  <link rel="stylesheet" href="ui.css">
</head>
<body>
  <div class="bg-grid"></div>

  <header class="topbar">
    <button class="pill-button" type="button" onclick="history.back()"><?php echo htmlspecialchars(t("common.back", "Zurück"), ENT_QUOTES, "UTF-8"); ?></button>
    <div class="brand">
      <img class="brand-logo" src="assets/FrisbeeCatch.png" alt="Ultimate Combine">
      <span class="brand-text"><?php echo htmlspecialchars(t("site.title", "Ultimate Combine"), ENT_QUOTES, "UTF-8"); ?></span>
      <span class="brand-sep">•</span>
      <span class="brand-team"><?php echo htmlspecialchars(t("impressum.brand", "Impressum"), ENT_QUOTES, "UTF-8"); ?></span>
    </div>
    <div class="topbar-actions">
      <details class="header-menu">
        <summary class="pill-button is-muted" aria-label="<?php echo htmlspecialchars(t("common.menu", "Menü"), ENT_QUOTES, "UTF-8"); ?>">☰</summary>
        <div class="menu-panel">
          <div class="menu-item">
            <span class="menu-label"><?php echo htmlspecialchars(t("common.theme", "Design"), ENT_QUOTES, "UTF-8"); ?></span>
            <button
              class="pill-button is-muted theme-toggle"
              type="button"
              data-theme-toggle
              data-theme-label-system="<?php echo htmlspecialchars(t("common.theme_auto", "Auto"), ENT_QUOTES, "UTF-8"); ?>"
              data-theme-label-dark="<?php echo htmlspecialchars(t("common.theme_dark", "Dunkel"), ENT_QUOTES, "UTF-8"); ?>"
              data-theme-label-light="<?php echo htmlspecialchars(t("common.theme_light", "Hell"), ENT_QUOTES, "UTF-8"); ?>"
              aria-pressed="false"
            ><?php echo htmlspecialchars(t("common.theme_auto", "Auto"), ENT_QUOTES, "UTF-8"); ?></button>
          </div>
          <div class="menu-item">
            <span class="menu-label"><?php echo htmlspecialchars(t("common.language", "Sprache"), ENT_QUOTES, "UTF-8"); ?></span>
            <div class="menu-links">
              <a class="pill-button is-muted<?php echo $lang === "de" ? " is-active" : ""; ?>" href="<?php echo htmlspecialchars(uc_lang_url("de"), ENT_QUOTES, "UTF-8"); ?>">DE</a>
              <a class="pill-button is-muted<?php echo $lang === "en" ? " is-active" : ""; ?>" href="<?php echo htmlspecialchars(uc_lang_url("en"), ENT_QUOTES, "UTF-8"); ?>">EN</a>
            </div>
          </div>
        </div>
      </details>
    </div>
  </header>

  <main class="team">
    <section class="auth-card">
      <h1><?php echo htmlspecialchars(t("impressum.heading", "Impressum"), ENT_QUOTES, "UTF-8"); ?></h1>
      <p class="lead"><?php echo htmlspecialchars(t("impressum.lead", "Angaben gemäß § 5 TMG und § 18 Abs. 2 MStV und ebenfalls Verantwortlich gemäß § 18 Abs. 2 MStV:"), ENT_QUOTES, "UTF-8"); ?></p>

      <h2><?php echo htmlspecialchars(t("impressum.operator_title", "Betreiber"), ENT_QUOTES, "UTF-8"); ?></h2>
      <p>
        <strong>Jakob Christen</strong><br>
        Mittelfeldstraße 13/3<br>
        71083 Herrenberg<br>
        Deutschland
      </p>

      <h2><?php echo htmlspecialchars(t("impressum.contact_title", "Kontakt"), ENT_QUOTES, "UTF-8"); ?></h2>
      <p>
        <?php echo htmlspecialchars(t("impressum.phone_label", "Telefon"), ENT_QUOTES, "UTF-8"); ?>: 07032/9543792<br>
        <?php echo htmlspecialchars(t("impressum.email_label", "E-Mail"), ENT_QUOTES, "UTF-8"); ?>: hello@ultimate-combine.de
      </p>

      <h2><?php echo htmlspecialchars(t("impressum.content_title", "Haftung für Inhalte"), ENT_QUOTES, "UTF-8"); ?></h2>
      <p><?php echo htmlspecialchars(t("impressum.content_body", "Als Diensteanbieter sind wir gemäß § 7 Abs. 1 TMG für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 TMG sind wir als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen. Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den allgemeinen Gesetzen bleiben hiervon unberührt. Eine diesbezügliche Haftung ist jedoch erst ab dem Zeitpunkt der Kenntnis einer konkreten Rechtsverletzung möglich. Bei Bekanntwerden von entsprechenden Rechtsverletzungen werden wir diese Inhalte umgehend entfernen."), ENT_QUOTES, "UTF-8"); ?></p>

      <h2><?php echo htmlspecialchars(t("impressum.links_title", "Haftung für Links"), ENT_QUOTES, "UTF-8"); ?></h2>
      <p><?php echo htmlspecialchars(t("impressum.links_body", "Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss haben. Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten verantwortlich. Die verlinkten Seiten wurden zum Zeitpunkt der Verlinkung auf mögliche Rechtsverstöße überprüft. Rechtswidrige Inhalte waren zum Zeitpunkt der Verlinkung nicht erkennbar. Eine permanente inhaltliche Kontrolle der verlinkten Seiten ist jedoch ohne konkrete Anhaltspunkte einer Rechtsverletzung nicht zumutbar. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Links umgehend entfernen."), ENT_QUOTES, "UTF-8"); ?></p>

      <h2><?php echo htmlspecialchars(t("impressum.copyright_title", "Urheberrecht"), ENT_QUOTES, "UTF-8"); ?></h2>
      <p><?php echo htmlspecialchars(t("impressum.copyright_body", "Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers. Downloads und Kopien dieser Seite sind nur für den privaten, nicht kommerziellen Gebrauch gestattet. Soweit die Inhalte auf dieser Seite nicht vom Betreiber erstellt wurden, werden die Urheberrechte Dritter beachtet. Insbesondere werden Inhalte Dritter als solche gekennzeichnet. Sollten Sie trotzdem auf eine Urheberrechtsverletzung aufmerksam werden, bitten wir um einen entsprechenden Hinweis. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Inhalte umgehend entfernen."), ENT_QUOTES, "UTF-8"); ?></p>
    </section>
  </main>

  <footer class="site-footer">
    <a class="footer-link" href="impressum.php"><?php echo htmlspecialchars(t("footer.impressum", "Impressum"), ENT_QUOTES, "UTF-8"); ?></a>
    <a class="footer-link" href="feedback.php"><?php echo htmlspecialchars(t("footer.feedback", "Feedback"), ENT_QUOTES, "UTF-8"); ?></a>
    <script type="text/javascript" src="https://cdnjs.buymeacoffee.com/1.0.0/button.prod.min.js" data-name="bmc-button" data-slug="jakob.christen" data-color="#ff7b4b" data-emoji="☕" data-font="Inter" data-text="Buy me a coffee" data-outline-color="#000000" data-font-color="#000000" data-coffee-color="#FFDD00"></script>
  </footer>
  <script src="theme.js"></script>
</body>
</html>
