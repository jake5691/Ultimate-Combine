<?php
require_once __DIR__ . "/bootstrap.php";
?>
<?php
$pageTitle = t("impressum.title", "Ultimate Combine – Impressum");
$pageLang = $lang;
require __DIR__ . "/partials/head.php";
$brandText = t("site.title", "Ultimate Combine");
$brandSuffix = t("impressum.brand", "Impressum");
$showBack = true;
$backOnclick = "history.back()";
require __DIR__ . "/partials/header-brand.php";
?>

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

  <?php require __DIR__ . "/partials/footer.php"; ?>
  <?php require __DIR__ . "/partials/foot.php"; ?>
