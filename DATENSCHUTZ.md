# Shoji: technische Grundlage für den Datenschutztext

(English version: [PRIVACY.md](PRIVACY.md). Optionen heißen `shoji.*`; die
Namen stehen im [README](README.md#configuration).)

Dieser Baustein beschreibt die Implementierung. Rechtsgrundlage, Interessenabwägung
und die Formulierung müssen vor Veröffentlichung für den konkreten Betrieb fachlich
geprüft und ergänzt werden. Der Text ist keine Rechtsberatung. Anbieter, die eine
Seite nicht nutzt, streichen Sie aus dem Text.

## Eingebettete Inhalte (Videos, Bestellformular) — technischer Textbaustein

Auf unserer Website binden wir Videos von YouTube und Vimeo sowie das
Bestellformular von Digistore24 ein. (Formulare im Seiten-HTML, etwa von
Brevo: eigener Baustein unten.) Diese Inhalte werden **nicht beim Aufruf
der Seite geladen.** Sie sehen zunächst nur einen Platzhalter mit einem
Vorschaubild, einer Schaltfläche und einem Hinweis. Das Vorschaubild liegt auf
unserem eigenen Server; Ihr Browser baut beim Besuch unserer Website keine
Verbindung zu YouTube, Vimeo oder Digistore24 auf. Es werden keine Cookies
gesetzt und keine Daten in Ihrem Browser gespeichert.

Erst wenn Sie auf den Platzhalter klicken, lädt Ihr Browser den Inhalt vom
jeweiligen Anbieter:

- **YouTube** (Google Ireland Limited, Gordon House, Barrow Street, Dublin 4,
  Irland): den Videoplayer im erweiterten Datenschutzmodus von
  `www.youtube-nocookie.com`. Datenschutzerklärung: https://policies.google.com/privacy
- **Vimeo** (Vimeo.com, Inc., 330 West 34th Street, New York, NY 10001, USA):
  den Videoplayer von `player.vimeo.com` mit der Einstellung „Do Not Track“.
  Datenschutzerklärung: https://vimeo.com/legal/privacy/policy
- **Digistore24** (Digistore24 GmbH, St.-Godehard-Straße 32, 31139 Hildesheim):
  das Bestellformular von `www.digistore24.com` und ein Skript von
  `www.digistore24-scripts.com`. Datenschutzerklärung: https://www.digistore24.com/page/privacy

Dabei erhält der Anbieter die Daten, die ein Browser bei jedem Abruf
übermittelt: Ihre IP-Adresse, Angaben zu Browser und Betriebssystem, Datum und
Uhrzeit sowie die Adresse unserer Seite. Der Anbieter kann innerhalb des
eingebetteten Inhalts Cookies setzen oder Daten in Ihrem Browser speichern.
Ab dem Klick gilt die Datenschutzerklärung des Anbieters. Bei YouTube und
Vimeo ist eine Übermittlung in die USA möglich.

Ihren Klick merken wir uns nicht: Auf der nächsten Seite und beim nächsten
Besuch sehen Sie wieder den Platzhalter.

Die Vorschaubilder ruft unser Server einmalig beim Anbieter ab und speichert
eine Kopie für 30 Tage. Dabei werden nur die Kennung des Videos und keine
Daten über Besucher übermittelt.

Ohne JavaScript ist die Schaltfläche ein gewöhnlicher Link, der Sie zum Inhalt
auf der Website des Anbieters führt.

## Formulare im Seiten-HTML (z. B. Brevo-Anmeldeformular) — technischer Textbaustein

Nur für Seiten, die ein Formular als Art `html` einbinden. Den Anbieter, seine
Anschrift und die Hosts aus dem eigenen Vertrag (Auftragsverarbeitung) bzw. der
Datenschutzerklärung des Anbieters übernehmen; Brevo ist ein Beispiel.

Für die Anmeldung zu unserem Newsletter nutzen wir ein Formular des Dienstes
**Brevo** ([Anbieter laut Ihrem Vertrag mit Brevo]). Datenschutzerklärung:
https://www.brevo.com/de/legal/privacypolicy/

Das Formular wird **nicht beim Aufruf der Seite geladen.** Sie sehen zunächst
nur einen Platzhalter mit einer Schaltfläche und einem Hinweis; Ihr Browser
baut dabei keine Verbindung zu Brevo auf. Erst wenn Sie auf die Schaltfläche
klicken, lädt Ihr Browser das Formular von unserem eigenen Server und danach
Skripte und Gestaltungsdateien von Brevo (`sibforms.com`). Dabei erhält Brevo
die Daten, die ein Browser bei jedem Abruf übermittelt: Ihre IP-Adresse,
Angaben zu Browser und Betriebssystem, Datum und Uhrzeit sowie die Adresse
unserer Seite.

Was Sie in das Formular eintragen, wird erst beim Absenden an Brevo
übermittelt. [Nur mit Kampagnenfeld:] Zusammen mit Ihren Eingaben übermittelt das Formular ein
verborgenes Feld mit der Kampagne, über die Sie auf unsere Seite gekommen
sind (etwa aus dem Link einer Anzeige), sofern es eine gibt. [Zweck und
Rechtsgrundlage der Kampagnenzuordnung ergänzen.]

Ihren Klick merken wir uns nicht: Auf der nächsten Seite und beim nächsten
Besuch sehen Sie wieder den Platzhalter.

## Hinweise für den Betreiber

- **Anbieterliste.** Der Baustein nennt die drei mitgelieferten Anbieter.
  Was die Seite nicht nutzt, streichen; eigene Anbieter (`shoji.providers`)
  ergänzen.
- **Vorschaubild-Abruf.** Der Server lädt Vorschaubilder von `img.youtube.com`
  bzw. über Vimeos öffentliche oEmbed-Schnittstelle. Es fließen keine
  Besucherdaten; nur Adressen, die die Seite selbst ausgegeben hat
  (signiert), lösen einen Abruf aus.
- **Gespeichert** wird nur auf dem Server: `storage/shoji/secret` (Zufallswert
  zum Signieren) und `storage/shoji/thumbs/` (Bildkopien, 30 Tage). Keine
  personenbezogenen Daten, kein Klick- oder Besuchsprotokoll.
- **Eigene Einbettungen.** Was ein Template selbst einbettet (`<iframe>`,
  Kirbys `video()`-Helfer, rohes HTML im Textfeld), deckt das Modul nicht ab.
  Die Seite mit einem Werkzeug prüfen, das das HTML liest, oder im
  Netzwerk-Tab des Browsers.
- **Formulare der Art `html`.** Das Formular-HTML stammt aus einem Snippet
  der Seite und kommt erst nach dem Klick über eine signierte Adresse
  (`/shoji/html/…`) vom eigenen Server; im Seiten-HTML steht davor kein
  Anbieter-Host. Skripte und Stylesheets des Anbieters lädt der Browser erst
  danach. Shoji selbst speichert nichts. Ein Seitenskript, das eine Kampagne
  in `sessionStorage` hält und in ein verborgenes Feld schreibt (z. B.
  `cam.js`), gehört nicht zu Shoji und muss im Datenschutztext eigens
  beschrieben werden (Speicherung im Browser; § 25 TDDDG prüfen).
- **`open`-Modus.** Wer per `open => true` Inhalte sofort lädt (etwa weil die
  Seite eine Einwilligung selbst speichert), muss das im Datenschutztext
  gesondert beschreiben; der Baustein oben gilt dann nicht.
