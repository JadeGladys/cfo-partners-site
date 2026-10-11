CFO PARTNERS - WDROZENIE STRONY v23

Wersja v23 ma domyslnie jezyk angielski. Polska wersja jest dostepna po kliknieciu PL.

Co zmienia v23:
- zostawia naprawe podwojnego wysylania formularza z frontendu
- uzywa stabilnego kodu SMTP z wersji v21
- endpoint formularza to contact_v23.php

PLIKI DO WRZUCENIA DO /home/cfo_partnersa/public_html/

Wymagane:
- .htaccess
- index.html
- contact.php
- contact_v23.php

Opcjonalne:
- CFO_PARTNERS_strona_interaktywna_v23_bilingual_EN_DEFAULT_SINGLE_SUBMIT_STABLE_SMTP.html
- VERSION_v23.txt
- README_WDROZENIE_v23.txt

MOZE ZOSTAC:
- index_wp_backup.php

NIE USUWAJ PLIKOW WORDPRESSA, jezeli chcesz zachowac mozliwosc powrotu do WordPressa:
- wp-admin
- wp-content
- wp-includes
- wp-config.php
- wp-login.php
- pozostale pliki wp-*.php

Po wrzuceniu plikow:
1. Wyczysc cache strony WWW w panelu hostingu.
2. Otworz https://www.cfo-partners.com.pl/contact_v23.php
   Powinien pojawic sie komunikat JSON, ze endpoint v23 dziala.
3. Otworz https://www.cfo-partners.com.pl/
4. Wyslij jeden test z formularza. Powinna przyjsc jedna wiadomosc.
