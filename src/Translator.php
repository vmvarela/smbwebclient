<?php

declare(strict_types=1);

namespace SmbWebClient;

class Translator
{
    private array $strings = [
        'af' => ['Windows Netwerk', 'Naam', 'Grootte', 'Opmerkings', 'Gewysig', 'Tipe', 'd/m/Y H:i', 'Druk \'n Lêer', 'Kanselleer gekose', 'Nuwe lêer', 'Kanselleer gekose werk', 'Gids', '', 'Stuur boodskap', 'Op', 'Nuwe gids'],
        'ar' => ['شبكة Windows', 'اسم', 'الحجم', 'تعليقات', 'التعديل', 'نوع', 'm/d/Y H:i', 'اطبع ملف', '', 'ملف جديد', '', '', '', '', '', ''],
        'az' => ['Windows Şəbəkəsi', 'Ad', 'Ölçü', 'Şərhlər', 'Dəyişdirildi', 'Tip', 'd.m.Y H:i', 'Çap Et', 'Seçilmişləri sil', 'Yeni dosya', 'Seçilmişləri ləğv et', 'Qovluq', '', 'Mesaj Göndər', 'Yuxarı', 'Yeni qovluq'],
        'bg' => ['Windows мрежа', 'Име', 'Размер', 'Коментари', 'Промяна', 'Тип', 'm/d/Y H:i', 'Печат', 'Изтрий избранште', 'Нов файл', 'Откажи избрано', 'Папка', 'Файл %s', 'Изпрати съобщение', 'Нагоре', 'Нова папка'],
        'bs' => ['Windows mreža', 'Ime', 'Veličina', 'Komentari', 'Promijenjeno', 'Tip', 'd/m/Y H:i', 'Ispis', 'Obriši odabrano', 'Nova datoteka', 'Poništi odabrano', 'Mapa', 'Datoteka %s', 'Pošalji poruku', 'Gore', 'Nova mapa'],
        'ca' => ['Xarxa Windows', 'Nom', 'Tamany', 'Comentaris', 'Modificat', 'Tipus', 'd/m/Y H:i', 'Imprimeix', 'Esborra els seleccionats', 'Nou arxiu', 'Cancel·la selecció', 'Carpeta', 'Fitxer %s', 'Enviar un missatge', 'Pujar', 'Nova carpeta'],
        'cs' => ['Síť Windows', 'Název', 'Velikost', 'Komentáře', 'Změněno', 'Typ', 'd.m.Y H:i', 'Tisk', 'Smazat vybrané', 'Nový soubor', 'Zrušit vybrané', 'Složka', 'Soubor %s', 'Poslat zprávu', 'Nahoru', 'Nová složka'],
        'da' => ['Windows Netværk', 'Navn', 'Størrelse', 'Kommentar', 'Ændret', 'Type', 'd/m/Y H:i', 'Udskriv', 'Slet valgte', 'Ny fil', 'Annullér valgte', 'Mappe', 'Fil %s', 'Send besked', 'Op', 'Ny mappe'],
        'de' => ['Windows Netzwerk', 'Name', 'Größe', 'Kommentare', 'Geändert', 'Typ', 'd.m.Y H:i', 'Drucken', 'Gewählte löschen', 'Neue Datei', 'Gewählte abbrechen', 'Ordner', 'Datei %s', 'Nachricht senden', 'Hoch', 'Neuer Ordner'],
        'el' => ['Δίκτυο Windows', 'Όνομα', 'Μέγεθος', 'Σχόλια', 'Τροποποιήθηκε', 'Τύπος', 'd/m/Y H:i', 'Εκτύπωση', 'Διαγραφή επιλεγμένων', 'Νέο αρχείο', 'Ακύρωση επιλογής', 'Φάκελος', 'Αρχείο %s', 'Αποστολή μηνύματος', 'Πάνω', 'Νέος φάκελος'],
        'en' => ['Windows Network', 'Name', 'Size', 'Comments', 'Modified', 'Type', 'm/d/Y H:i', 'Print a file', 'Delete selected items', 'New file (upload)', 'Cancel selected jobs', 'Folder', 'File %s', 'Send a message', 'Up', 'New folder', 'Download this folder', 'Logout', 'Username', 'Password', 'Language', 'Connect', 'Theme', 'Drop files here to upload or click', 'Warning', 'Please select at least one item.', 'Close', 'Confirm delete', 'Are you sure you want to delete: %s? Only files and empty folders can be deleted.', 'Cancel', 'Create', 'Delete', 'Folder name:', 'Please enter a folder name'],
        'eo' => ['Reto de Windows', 'Nomo', 'Grandeco', 'Komentaroj', 'Modifii', '', '', 'Presi', '', 'Nova dosiero', 'Nuligi', '', '', '', '', ''],
        'es' => ['Red Windows', 'Nombre', 'Tamaño', 'Comentarios', 'Modificado', 'Tipo', 'd/m/Y H:i', 'Imprimir', 'Borrar seleccionados', 'Nuevo archivo', 'Cancelar seleccionados', 'Carpeta', 'Archivo %s', 'Enviar un mensaje', 'Subir', 'Nueva carpeta', 'Descargar esta carpeta', 'Desconectar', 'Usuario', 'Contraseña', 'Idioma', 'Conectar', 'Tema'],
        'et' => ['Windowsi võrk', 'Nimi', 'Suurus', 'Kommentaarid', 'Muudetud', 'Tüüp', 'd/m/Y H:i', 'Trüki', 'Kustuta valitud', 'Uus fail', 'Tühista valitud', 'Kataloog', 'Fail %s', 'Saada popup teade', 'Üles', 'Uus kataloog'],
        'eu' => ['Windows Sarea', 'Izena', 'Tamaina', 'Komentarioak', 'Aldatua', 'Mota', 'd/m/Y H:i', 'Inprimatu', 'Ezabatu aukeratuak', 'Fitxategi berria', 'Ezeztatu hautespena', 'Karpeta', 'Fitxategia %s', 'Bidali mezua', 'Gora', 'Karpeta berria'],
        'fa' => ['شبکه ویندوز', 'نام', 'اندازه', 'نظرات', 'اصلاح شده', 'نوع', 'd/m/Y H:i', 'چاپ', 'حذف انتخاب شده', 'پرونده جدید', 'لغو انتخاب', 'پوشه', 'پرونده %s', 'ارسال پیام', 'بالا', 'پوشه جدید'],
        'fi' => ['Windows Verkko', 'Nimi', 'Koko', 'Kommentit', 'Muokattu', 'Tyyppi', 'p/k/v t:m', 'Tulosta', 'Poista valitut', 'Uusi tiedosto', 'Peruuta valinta', 'Kansio', 'Tiedosto %s', 'Lähetä popup viesti', 'Ylös', 'Uusi kansio'],
        'fr' => ['Réseau Windows', 'Nom', 'Taille', 'Commentaire', 'Modifié', 'Type', 'd/m/Y H:i', 'Imprimer', 'Effacer la sélection', 'Nouveau fichier (envoi)', 'Annuler la sélection', 'Dossier', 'Fichier %s', 'Envoyer un message popup', 'Remonter', 'Nouveau dossier', 'Télécharger ce dossier', 'Déconnexion', 'Utilisateur', 'Mot de passe', 'Langue', 'Connecter', 'Thème'],
        'gl' => ['Rede Windows', 'Nome', 'Tamaño', 'Comentarios', 'Modificado', 'Tipo', 'd/m/Y H:i', 'Imprimir', 'Borrar seleccionados', 'Novo arquivo', 'Cancelar seleccionados', 'Carpeta', 'Arquivo %s', 'Enviar mensaxe', 'Arriba', 'Nova carpeta'],
        'he' => ['רשת Windows', 'שם', 'גודל', 'הערות', 'שונה', 'סוג', 'd/m/Y H:i', 'הדפסה', 'מחק נבחרים', 'קובץ חדש', 'בטל נבחרים', 'תיקייה', 'קובץ %s', 'שלח הודעה', 'למעלה', 'תיקייה חדשה'],
        'hi' => ['Windows नेटवर्क', 'नाम', 'आकार', 'टिप्पणियां', 'संशोधित', 'प्रकार', 'd/m/Y H:i', 'प्रिंट', 'चयनित को हटाएं', 'नई फ़ाइल', 'चयनित को रद्द करें', 'फ़ोल्डर', 'फ़ाइल %s', 'संदेश भेजें', 'ऊपर', 'नया फ़ोल्डर'],
        'hr' => ['Windows mreža', 'Naziv', 'Veličina', 'Komentar', 'Modificirano', 'Tip', 'd/m/Y H:i', 'Ispiši', 'Obriši selektirano', 'Nova datoteka', 'Otkazi selektirano', 'Mapa', 'Datoteka %s', 'Pošalji poruku', 'Gore', 'Nova mapa'],
        'hu' => ['Windows hálózat', 'Név', 'Méret', 'Megjegyzés', 'Módosítva', 'Típus', 'd/m/Y H:i', 'Nyomtat', 'Kiválasztottak törlése', 'Új állomány', 'Kijelölés elvetése', 'Mappa', 'Fájl %s', 'Előugró üzenet küldése', 'Fel', 'Új mappa'],
        'id' => ['Jaringan Windows', 'Nama', 'Ukuran', 'Komentar', 'Terakhir diubah', 'Tipe', 'm/d/Y H:i', 'Cetak file ini', 'Hapus item yang dipilih', 'File baru (upload)', 'Batalkan pekerjaan yang dipilih', 'File dalam folder', 'File %s', 'Kirim pesan popup', 'Naik', 'Folder baru'],
        'it' => ['Rete Windows', 'Nome', 'Dimensione', 'Commenti', 'Modificato', 'Tipo', 'd/m/Y H:i', 'Stampa', 'Cancella selezionati', 'Nuovo file', 'Annulla selezionati', 'Cartella', 'File %s', 'Invia messaggio', 'Su', 'Nuova cartella'],
        'ja' => ['ネットワーク', '名前', 'サイズ', 'コメント', '更新日時', 'タイプ', 'Y/m/d H:i', 'プリント', '選択したものを削除', '新規作成', '選択したものをキャンセル', 'ファイルフォルダ', 'ファイル %s', 'ポップアップメッセージの送信', 'フォルダ内へ', '新規フォルダ', 'ログアウト'],
        'ko' => ['Windows 네트워크', '이름', '크기', '설명', '수정됨', '종류', 'Y/m/d H:i', '인쇄', '선택한 항목 삭제', '새 파일', '선택한 항목 취소', '폴더', '파일 %s', '팝업 메시지 보내기', '위로', '새 폴더'],
        'ka' => ['Windows ქსელი', 'სახელი', 'ზომა', 'კომენტარი', 'შეცვლილი', 'ტიპი', 'd/m/Y H:i', 'ბეჭდვა', 'წაშლა არჩეული', 'ახალი ფайლი', 'გაუქმება არჩეული', 'ფოლდერი', 'ფაილი %s', 'შეტყობინების გაგზავნა', 'ზე', 'ახალი ფოლდერი'],
        'lt' => ['Windows Tinklas', 'Vardas', 'Dydis', 'Komentarai', 'Pakeista', 'Tipas', 'd/m/Y H:i', 'Spausdinti bylą', 'Trinti parinktus', 'Įkelti bylą', 'Nutraukti parinktas užduotis', 'Katalogas', 'Byla %s', 'Siųsti žinutę', 'Aukštyn', 'Naujas katalogas'],
        'lv' => ['Windows Tīkls', 'Nosaukums', 'Izmērs', 'Komentāri', 'Izmainīts', 'Tips', 'd/m/Y H:i', 'Drukāt', 'Dzēst izvēlētos', 'Jauns fails', 'Atcelt izvēlētos', 'Mape', 'Fails %s', 'Sūtīt ziņu', 'Augšup', 'Jauna mape'],
        'ms' => ['Rangkaian Windows', 'Nama', 'Saiz', 'Ulasan', 'Diubah', 'Jenis', 'd/m/Y H:i', 'Cetak', 'Padam pilihan', 'Fail baru', 'Batal pilihan', 'Folder', 'Fail %s', 'Hantar mesej', 'Atas', 'Folder baru'],
        'nl' => ['Windows Netwerk', 'Naam', 'Grootte', 'Opmerking', 'Gewijzigd', 'Type', 'd-m-Y h:i', 'Afdrukken', 'Verwijder geselecteerde', 'Nieuw bestand', 'Annuleer geselecteerde', 'Bestandsmap', 'Bestand %s', 'Stuur pop-upbericht', 'Omhoog', 'Nieuwe map', 'Download map'],
        'no' => ['Windowsnettverk', 'Navn', 'Størrelse', 'Kommentar', 'Endret', 'Type', 'd-m-Y h:i', 'Utskrift', 'Slett valgte', 'Ny fil', 'Avbryt valgte', 'Filmappe', 'Fil %s', 'Send popup-melding', 'Opp', 'Ny mappe'],
        'pl' => ['Sieć Windows', 'Nazwa', 'Rozmiar', 'Komentarze', 'Zmieniono', 'Typ', 'd.m.Y H:i', 'Drukuj', 'Usuń wybrane', 'Nowy plik', 'Anuluj wybrane', 'Folder', 'Plik %s', 'Wyślij wiadomość', 'Góra', 'Nowy folder'],
        'pt-br' => ['Rede Windows', 'Nome', 'Tamanho', 'Comentários', 'Modificado', 'Tipo', 'd/m/Y H:i', 'Imprimir', 'Apagar selecionados', 'Novo arquivo', 'Cancelar Seleção', 'Pasta de arquivo', 'Arquivo %s', 'Enviar Mensagem', 'Acima', 'Nova Pasta', 'Descarregar pasta'],
        'pt' => ['Rede windows', 'Nome', 'Tamanho', 'Comentário', 'Modificado', 'Tipo', 'd/m/Y h:i', 'Imprimir Ficheiro', 'Apagar a Seleção', 'Novo Ficheiro', 'Cancelar a Seleção', 'Ficheiro', 'Ficheiro %s', 'Enviar uma mensagem Instantânea', 'Para Cima', 'Nova Pasta'],
        'ro' => ['Rețea Windows', 'Nume', 'Dimensiune', 'Comentarii', 'Modificat', 'Tip', 'd/m/Y H:i', 'Tipărire', 'Șterge selectate', 'Fișier nou', 'Anulați selectate', 'Folder', 'Fișier %s', 'Trimiteți mesaj', 'Sus', 'Folder nou'],
        'ru' => ['Сеть Windows', 'Название', 'Размер', 'Комментарии', 'Изменено', 'Тип', 'd/m/Y H:i', 'Печать', 'Удалить выделенные', 'Новый файл (поместить)', 'Отменить', 'Папка файла', 'Файл %s', 'Послать сообщение', 'Вверх', 'Новая папка', 'Скачать папку'],
        'sk' => ['Sieť Windows', 'Meno', 'Veľkosť', 'Poznámky', 'Zmenené', 'Typ', 'd/m/Y H:i', 'Vytlačiť súbor', 'Vymazať vybrané položky', 'Nový súbor (upload)', 'Zrušiť vybrané úlohy', 'Adresár', 'Súbor %s', 'Poslať pop-up správu', 'Hore', 'Nový adresár'],
        'sl' => ['Windows Omrežje', 'Ime', 'Velikost', 'Komentarji', 'Sprememba', 'Tip', 'd/m/Y H:i', 'Natisni', 'Izbriši izbrano', 'Nova datoteka', 'Prekliči izbrano', 'Mapa', 'Datoteka %s', 'Pošlji sporočilo', 'Gor', 'Nova mapa'],
        'sq' => ['Rrjeti Windows', 'Emri', 'Madhësia', 'Komente', 'I ndryshuar', 'Lloji', 'd/m/Y H:i', 'Printim', 'Fshij të zgjedhurit', 'Skedar i ri', 'Anulo të zgjedhurit', 'Dosje', 'Skedar %s', 'Dërgo mesazh', 'Lart', 'Dosje e re'],
        'sr' => ['Windows mreža', 'Ime', 'Veličina', 'Komentari', 'Promenjen', 'Tip', 'd/m/Y H:i', 'Odštampaj', 'Obriši selektovano', 'Nova datoteka', 'Odustajem od izabranog', 'Direktorijum', 'Datoteka %s', 'Pošalji poruku', 'Nazad', 'Novi direktorijum'],
        'sv' => ['Windows nätverk', 'Namn', 'Storlek', 'Kommentarer', 'Ändrad', 'Typ', 'd/m/Y H:i', 'Skriv ut', 'Radera markerad', 'Ny fil', 'Avbryt markerad', 'Mapp', 'Fil %s', 'Skicka meddelande', 'Upp', 'Ny mapp'],
        'th' => ['ระบบเครือข่าย Samba', 'ชื่อ', 'ขนาด', 'ความเห็น', 'ปรับปรุง', 'ชนิด', 'เดือน/วัน/ปี ชั่วโมง:นาที', 'พิมพ์ File', 'ลบรายการที่เลือก', 'ไฟล์ใหม่', 'ยกเลิกรายการที่เลือก', '', '', 'ส่งข้อความ Popup', 'ขึ้น', 'สร้าง Folder ใหม่'],
        'tr' => ['Windows Ağı', 'İsim', 'Boyut', 'Yorumlar', 'Değiştirilme Tarihi', 'Tip', 'd/m/Y h:i', 'Yazdır', 'Seçimi sil', 'Yeni dosya', 'Seçim İptal', 'Dosya Dizini', 'Dosya boyutu', 'Uyarı iletisi gönder', 'Yukarı', 'Yeni dizin'],
        'uk' => ['Мережа Вінворіз', 'Ім\'я', 'Розмір', 'Коментарії', 'Змінений', 'Тип', 'd.m.Y h:i', 'Друкувати', 'Видалити відмічене', 'Новий файл', 'Відмінити відмічене', 'Папка', 'Файл %s', '', 'Виділити всі', 'Нова папка'],
        'zh-tw' => ['觀窗網路', '名稱', '大小', '說明', '修正', '型式', 'd/m/Y H:i', '列印', '刪除選擇項', '新檔案(上傳)', '取消選擇的工作', '檔案資料夾', '檔案%s', '傳送訊息', '上一階', '新資料夾'],
        'zh' => ['Windows网络', '姓名', '大小', '注释', '修改', '类型', '月/日/年 时:分', '打印一个文件', '删除选择项目', '新建文件(上传)', '取消选择对象', '文件夹', '文件', '发送一个弹出消息', '向上', '新建文件夹'],
    ];

    public function __construct(
        private readonly string $language,
    ) {
    }

    public function translate(int $index, ...$args): string
    {
        $string = $this->strings[$this->language][$index] 
            ?? $this->strings['en'][$index] 
            ?? "String $index";
        
        if (!empty($args)) {
            return sprintf($string, ...$args);
        }
        
        return $string;
    }

    public function detectLanguage(string $default = 'en'): string
    {
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        
        foreach (explode(',', $acceptLanguage) as $lang) {
            $lang = strtolower(trim(explode(';', $lang)[0]));
            $lang = explode('-', $lang)[0];
            
            if (isset($this->strings[$lang])) {
                return $lang;
            }
        }
        
        return $default;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getAvailableLanguages(): array
    {
        return [
            'af' => 'Afrikaans',
            'ar' => 'العربية',
            'az' => 'Azərbaycanca',
            'bg' => 'Български',
            'bs' => 'Bosanski',
            'ca' => 'Català',
            'cs' => 'Čeština',
            'da' => 'Dansk',
            'de' => 'Deutsch',
            'el' => 'Ελληνικά',
            'en' => 'English',
            'eo' => 'Esperanto',
            'es' => 'Español',
            'et' => 'Eesti',
            'eu' => 'Euskera',
            'fa' => 'فارسی',
            'fi' => 'Suomi',
            'fr' => 'Français',
            'gl' => 'Galego',
            'he' => 'עברית',
            'hi' => 'हिन्दी',
            'hr' => 'Hrvatski',
            'hu' => 'Magyar',
            'id' => 'Bahasa Indonesia',
            'it' => 'Italiano',
            'ja' => '日本語',
            'ko' => '한국어',
            'ka' => 'ქართული',
            'lt' => 'Lietuvių',
            'lv' => 'Latvian',
            'ms' => 'Bahasa Melayu',
            'nl' => 'Nederlands',
            'no' => 'Norsk',
            'pl' => 'Polski',
            'pt-br' => 'Português Brasileiro',
            'pt' => 'Português',
            'ro' => 'Română',
            'ru' => 'Русский',
            'sk' => 'Slovenčina',
            'sl' => 'Slovenščina',
            'sq' => 'Shqip',
            'sr' => 'Српски',
            'sv' => 'Svenska',
            'th' => 'ไทย',
            'tr' => 'Türkçe',
            'uk' => 'Українська',
            'zh-tw' => '繁體中文',
            'zh' => '简体中文'
        ];
    }
}
