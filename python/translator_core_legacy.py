import os
import re, time, zipfile, shutil, tempfile
from lxml import etree

# ── Namespace XML docx ─────────────────────────────────────────────────────────
W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'

# ── Konfigurasi batch Google Translate ────────────────────────────────────────
# Semakin besar = semakin cepat, tapi jangan melebihi 4500 karakter per batch
BATCH_SIZE = 20          # Paragraf per batch
SEPARATOR  = ' ||| '     # Pemisah antar paragraf (pendek, tidak ambigu)
BATCH_DELAY = 0.2        # Detik jeda antar batch (hindari block Google)

# ── Cache & Stats ──────────────────────────────────────────────────────────────
translate_cache: dict[str, str] = {}
stats = {'translated': 0, 'cached': 0, 'skipped': 0, 'errors': 0}


# ══════════════════════════════════════════════════════════════════════════════
# DICTIONARY AMERICAN → BRITISH ENGLISH
# Mencakup: ejaan umum, akademik, hukum, sains, administrasi
# Format: r'\bkata\b' → 'kata_british'
# ══════════════════════════════════════════════════════════════════════════════
AM_TO_BR: dict[str, str] = {

    # ── -ize → -ise ────────────────────────────────────────────────────────────
    r'\bacclimatize\b': 'acclimatise',
    r'\bagonize\b': 'agonise',
    r'\banalyze\b': 'analyse',       r'\banalyzed\b': 'analysed',
    r'\banalyzing\b': 'analysing',   r'\banalyzes\b': 'analyses',
    r'\bauthorize\b': 'authorise',   r'\bauthorized\b': 'authorised',
    r'\bauthorizing\b': 'authorising', r'\bauthorization\b': 'authorisation',
    r'\bcapitalize\b': 'capitalise', r'\bcapitalized\b': 'capitalised',
    r'\bcategorize\b': 'categorise', r'\bcategorized\b': 'categorised',
    r'\bcategorizing\b': 'categorising',
    r'\bcharacterize\b': 'characterise', r'\bcharacterized\b': 'characterised',
    r'\bcivilize\b': 'civilise',     r'\bcivilized\b': 'civilised',
    r'\bcolonize\b': 'colonise',     r'\bcolonized\b': 'colonised',
    r'\bcommercialize\b': 'commercialise',
    r'\bcriminalize\b': 'criminalise', r'\bcriminalized\b': 'criminalised',
    r'\bcustomize\b': 'customise',   r'\bcustomized\b': 'customised',
    r'\bdemocratize\b': 'democratise',
    r'\bdigitize\b': 'digitise',     r'\bdigitized\b': 'digitised',
    r'\bdramatize\b': 'dramatise',
    r'\bemphasize\b': 'emphasise',   r'\bemphasized\b': 'emphasised',
    r'\bemphasizing\b': 'emphasising',
    r'\benergize\b': 'energise',
    r'\bequalize\b': 'equalise',     r'\bequalizing\b': 'equalising',
    r'\bfertilize\b': 'fertilise',   r'\bfertilized\b': 'fertilised',
    r'\bfinalize\b': 'finalise',     r'\bfinalized\b': 'finalised',
    r'\bformalise\b': 'formalise',
    r'\bformalize\b': 'formalise',   r'\bformalized\b': 'formalised',
    r'\bglobalize\b': 'globalise',   r'\bglobalized\b': 'globalised',
    r'\bglobalization\b': 'globalisation',
    r'\bharmonize\b': 'harmonise',   r'\bharmonized\b': 'harmonised',
    r'\bhypnotize\b': 'hypnotise',
    r'\bimmobilize\b': 'immobilise',
    r'\bimprovise\b': 'improvise',
    r'\bindustrialize\b': 'industrialise', r'\bindustrialized\b': 'industrialised',
    r'\binitialize\b': 'initialise',
    r'\binstitionalize\b': 'institutionalise',
    r'\binstitutionalize\b': 'institutionalise',
    r'\blegalize\b': 'legalise',     r'\blegalized\b': 'legalised',
    r'\bliberalize\b': 'liberalise', r'\bliberalized\b': 'liberalised',
    r'\blocalize\b': 'localise',     r'\blocalized\b': 'localised',
    r'\bmaximize\b': 'maximise',     r'\bmaximized\b': 'maximised',
    r'\bmemorize\b': 'memorise',     r'\bmemorized\b': 'memorised',
    r'\bminimize\b': 'minimise',     r'\bminimized\b': 'minimised',
    r'\bmodernize\b': 'modernise',   r'\bmodernized\b': 'modernised',
    r'\bmonopolize\b': 'monopolise',
    r'\bnationalize\b': 'nationalise', r'\bnationalized\b': 'nationalised',
    r'\bneutralize\b': 'neutralise',
    r'\bnormalize\b': 'normalise',   r'\bnormalized\b': 'normalised',
    r'\boptimize\b': 'optimise',     r'\boptimized\b': 'optimised',
    r'\boptimizing\b': 'optimising',
    r'\borganize\b': 'organise',     r'\borganized\b': 'organised',
    r'\borganizing\b': 'organising', r'\borganization\b': 'organisation',
    r'\borganizations\b': 'organisations',
    r'\bparalyze\b': 'paralyse',     r'\bparalyzed\b': 'paralysed',
    r'\bpasteurize\b': 'pasteurise',
    r'\bpenalize\b': 'penalise',     r'\bpenalized\b': 'penalised',
    r'\bpersonalize\b': 'personalise',
    r'\bpolarize\b': 'polarise',
    r'\bpopularize\b': 'popularise',
    r'\bprioritize\b': 'prioritise', r'\bprioritized\b': 'prioritised',
    r'\bprioritizing\b': 'prioritising',
    r'\bprivatize\b': 'privatise',   r'\bprivatized\b': 'privatised',
    r'\bprivatization\b': 'privatisation',
    r'\brationalize\b': 'rationalise',
    r'\brealize\b': 'realise',       r'\brealized\b': 'realised',
    r'\brealizing\b': 'realising',   r'\brealization\b': 'realisation',
    r'\brecognize\b': 'recognise',   r'\brecognized\b': 'recognised',
    r'\brecognizing\b': 'recognising',
    r'\bregularize\b': 'regularise',
    r'\breorganize\b': 'reorganise', r'\breorganized\b': 'reorganised',
    r'\brevitalize\b': 'revitalise',
    r'\bsecularize\b': 'secularise',
    r'\bsensitize\b': 'sensitise',
    r'\bsocialize\b': 'socialise',   r'\bsocialized\b': 'socialised',
    r'\bspecialize\b': 'specialise', r'\bspecialized\b': 'specialised',
    r'\bspecializing\b': 'specialising', r'\bspecialization\b': 'specialisation',
    r'\bstabilize\b': 'stabilise',   r'\bstabilized\b': 'stabilised',
    r'\bstandardize\b': 'standardise', r'\bstandardized\b': 'standardised',
    r'\bstandardization\b': 'standardisation',
    r'\bsummarize\b': 'summarise',   r'\bsummarized\b': 'summarised',
    r'\bsymbolize\b': 'symbolise',
    r'\bsynchronize\b': 'synchronise',
    r'\bsystematize\b': 'systematise',
    r'\btheorize\b': 'theorise',
    r'\butilize\b': 'utilise',       r'\butilized\b': 'utilised',
    r'\butilizing\b': 'utilising',   r'\butilization\b': 'utilisation',
    r'\bvictimize\b': 'victimise',
    r'\bvisualize\b': 'visualise',   r'\bvisualized\b': 'visualised',
    r'\bvocalize\b': 'vocalise',
    r'\bwesternize\b': 'westernise',

    # ── -or → -our ─────────────────────────────────────────────────────────────
    r'\barbor\b': 'arbour',
    r'\barmor\b': 'armour',          r'\barmored\b': 'armoured',
    r'\bbehavior\b': 'behaviour',    r'\bbehaviors\b': 'behaviours',
    r'\bclamor\b': 'clamour',
    r'\bcolor\b': 'colour',          r'\bcolors\b': 'colours',
    r'\bcolored\b': 'coloured',      r'\bcolorful\b': 'colourful',
    r'\bdemeanor\b': 'demeanour',
    r'\bendeavor\b': 'endeavour',    r'\bendeavors\b': 'endeavours',
    r'\bfavor\b': 'favour',          r'\bfavors\b': 'favours',
    r'\bfavorable\b': 'favourable',  r'\bfavorably\b': 'favourably',
    r'\bfavorite\b': 'favourite',    r'\bfavorites\b': 'favourites',
    r'\bfervor\b': 'fervour',
    r'\bflavor\b': 'flavour',        r'\bflavors\b': 'flavours',
    r'\bglamor\b': 'glamour',
    r'\bharbor\b': 'harbour',        r'\bharbors\b': 'harbours',
    r'\bhonor\b': 'honour',          r'\bhonors\b': 'honours',
    r'\bhonorable\b': 'honourable',  r'\bhonorably\b': 'honourably',
    r'\bhumor\b': 'humour',          r'\bhumors\b': 'humours',
    r'\bhumorous\b': 'humorous',
    r'\blabor\b': 'labour',          r'\blabors\b': 'labours',
    r'\blaborer\b': 'labourer',      r'\blaborers\b': 'labourers',
    r'\bneighbor\b': 'neighbour',    r'\bneighbors\b': 'neighbours',
    r'\bneighborhood\b': 'neighbourhood',
    r'\bodor\b': 'odour',
    r'\bramor\b': 'ramour',
    r'\brigor\b': 'rigour',          r'\brigorous\b': 'rigorous',
    r'\bsavor\b': 'savour',
    r'\bsplendor\b': 'splendour',
    r'\btremor\b': 'tremor',
    r'\btumor\b': 'tumour',          r'\btumors\b': 'tumours',
    r'\bvapor\b': 'vapour',          r'\bvapors\b': 'vapours',
    r'\bvigor\b': 'vigour',          r'\bvigorous\b': 'vigorous',

    # ── -er → -re ──────────────────────────────────────────────────────────────
    r'\bcenter\b': 'centre',         r'\bcenters\b': 'centres',
    r'\bcentered\b': 'centred',
    r'\bfiber\b': 'fibre',           r'\bfibers\b': 'fibres',
    r'\bliter\b': 'litre',           r'\bliters\b': 'litres',
    r'\bmeager\b': 'meagre',
    r'\bmeter\b': 'metre',           r'\bmeters\b': 'metres',
    r'\bsomber\b': 'sombre',
    r'\bspecter\b': 'spectre',
    r'\btheater\b': 'theatre',       r'\btheaters\b': 'theatres',

    # ── -ense → -ence ──────────────────────────────────────────────────────────
    r'\bdefense\b': 'defence',       r'\bdefenses\b': 'defences',
    r'\boffense\b': 'offence',       r'\boffenses\b': 'offences',
    r'\bpretense\b': 'pretence',

    # ── double-L ───────────────────────────────────────────────────────────────
    r'\bcanceled\b': 'cancelled',    r'\bcanceling\b': 'cancelling',
    r'\bcancellation\b': 'cancellation',
    r'\bcounselor\b': 'counsellor',  r'\bcounselors\b': 'counsellors',
    r'\bdialog\b': 'dialogue',       r'\bdialogs\b': 'dialogues',
    r'\benrolled\b': 'enrolled',
    r'\benrollment\b': 'enrolment',
    r'\bfulfill\b': 'fulfil',        r'\bfulfilled\b': 'fulfilled',
    r'\bfulfillment\b': 'fulfilment',
    r'\bfueled\b': 'fuelled',        r'\bfueling\b': 'fuelling',
    r'\binstall\b': 'instal',
    r'\binstallment\b': 'instalment',
    r'\bjeweler\b': 'jeweller',      r'\bjewelry\b': 'jewellery',
    r'\blabeled\b': 'labelled',      r'\blabeling\b': 'labelling',
    r'\bmodeled\b': 'modelled',      r'\bmodeling\b': 'modelling',
    r'\bsignaling\b': 'signalling',  r'\bsignaled\b': 'signalled',
    r'\bskilled\b': 'skilled',
    r'\btraveled\b': 'travelled',    r'\btraveling\b': 'travelling',
    r'\btraveler\b': 'traveller',    r'\btravelers\b': 'travellers',
    r'\bworship\b': 'worship',
    r'\bworshiped\b': 'worshipped',

    # ── Misc ejaan ─────────────────────────────────────────────────────────────
    r'\banalog\b': 'analogue',
    r'\banesthesia\b': 'anaesthesia', r'\banesthetic\b': 'anaesthetic',
    r'\bcatalog\b': 'catalogue',     r'\bcatalogs\b': 'catalogues',
    r'\bcheque\b': 'cheque',
    r'\bcozy\b': 'cosy',
    r'\bencyclopedia\b': 'encyclopaedia',
    r'\bgray\b': 'grey',             r'\bgrays\b': 'greys',
    r'\bgrayish\b': 'greyish',
    r'\bmaneuver\b': 'manoeuvre',    r'\bmaneuvers\b': 'manoeuvres',
    r'\bmedieval\b': 'mediaeval',
    r'\bmonolog\b': 'monologue',
    r'\bpaediatrician\b': 'paediatrician',
    r'\bpediatric\b': 'paediatric',
    r'\bpediatrician\b': 'paediatrician',
    r'\bprolog\b': 'prologue',
    r'\bplow\b': 'plough',
    r'\bskeptical\b': 'sceptical',   r'\bskeptic\b': 'sceptic',
    r'\bskepticism\b': 'scepticism',
    r'\btire\b': 'tyre',             r'\btires\b': 'tyres',
    r'\bwisdom\b': 'wisdom',

    # ── Program / Programme ────────────────────────────────────────────────────
    # Catatan: 'program' dalam konteks komputer tetap 'program' di British
    # Di sini kita koreksi konteks non-komputer
    r'\bprograms\b': 'programmes',
    r'\bprogram\b': 'programme',

    # ── Licence / License ──────────────────────────────────────────────────────
    # Noun: licence | Verb: license (sama di British)
    r'\blicensed\b': 'licenced',
    r'\blicensing\b': 'licencing',

    # ── Akademik & Formal ──────────────────────────────────────────────────────
    r'\bdownward\b': 'downwards',
    r'\bupward\b': 'upwards',
    r'\bforward\b': 'forwards',
    r'\bbackward\b': 'backwards',
    r'\btoward\b': 'towards',
    r'\bafterward\b': 'afterwards',
    r'\bin the hospital\b': 'in hospital',
    r'\bmath\b': 'maths',
    r'\bfall semester\b': 'autumn semester',
    r'\bfall term\b': 'autumn term',
    r'\bwhile\b': 'whilst',          # opsional — whilst lebih formal
    r'\bamong\b': 'amongst',

    # ── Hukum & Administrasi ───────────────────────────────────────────────────
    r'\bjudgment\b': 'judgement',
    r'\backnowledgment\b': 'acknowledgement',
    r'\babridgment\b': 'abridgement',
    r'\babridgement\b': 'abridgement',
}


def apply_british_dictionary(text: str) -> str:
    """Terapkan semua aturan American→British dengan mempertahankan kapitalisasi."""
    for pattern, replacement in AM_TO_BR.items():
        def _replace(m, repl=replacement):
            word = m.group(0)
            if word.isupper():
                return repl.upper()
            elif word[0].isupper():
                return repl[0].upper() + repl[1:]
            return repl
        text = re.sub(pattern, _replace, text, flags=re.IGNORECASE)
    return text


# print('✅ Engine siap!')
# print(f'   • Google Translate   : aktif (gratis, tanpa API key)')
# print(f'   • British dictionary : {len(AM_TO_BR)} pola koreksi')
# print(f'   • Batch size         : {BATCH_SIZE} paragraf/call')
# print(f'   • LLM                : tidak digunakan (mode turbo)')

def is_translatable(text: str) -> bool:
    text = text.strip()
    if not text or len(text) < 2:
        return False
    if re.fullmatch(r'[\d\s\.,;:!?@#$%^&*()\-_=+\[\]{}|<>/\\"\'\'\"…•·°±×÷≤≥≠≈∞%]+', text):
        return False
    if re.match(r'https?://', text) or ('@' in text and '.' in text and ' ' not in text):
        return False
    if not re.search(r'[a-zA-Z]', text):
        return False
    return True


def google_translate_paragraphs(texts: list[str]) -> list[str]:
    """
    Terjemahkan list teks dengan Google Translate secara batch.
    Paragraf digabung dengan SEPARATOR, dikirim sekaligus, lalu dipisah kembali.
    """
    results      = [''] * len(texts)
    to_translate = []   # (index_asli, teks)

    for i, text in enumerate(texts):
        if text in translate_cache:
            results[i] = translate_cache[text]
            stats['cached'] += 1
        elif is_translatable(text):
            to_translate.append((i, text))
        else:
            results[i] = text
            stats['skipped'] += 1

    # Proses dalam batch
    for batch_start in range(0, len(to_translate), BATCH_SIZE):
        batch = to_translate[batch_start : batch_start + BATCH_SIZE]
        indices, batch_texts = zip(*batch)

        # Gabungkan dengan separator
        combined = SEPARATOR.join(batch_texts)

        # Cek panjang karakter (Google Translate limit ~5000 char)
        # Jika terlalu panjang, split jadi 2 sub-batch
        if len(combined) > 4500:
            mid = len(batch) // 2
            # Rekursif dengan batch lebih kecil
            sub1_texts = [t for _, t in batch[:mid]]
            sub2_texts = [t for _, t in batch[mid:]]
            sub1_idx   = list(indices[:mid])
            sub2_idx   = list(indices[mid:])

            for sub_texts, sub_idx in [(sub1_texts, sub1_idx), (sub2_texts, sub2_idx)]:
                sub_combined = SEPARATOR.join(sub_texts)
                try:
                    from deep_translator import GoogleTranslator
                    translated = GoogleTranslator(source='id', target='en').translate(sub_combined)
                    parts = [p.strip() for p in translated.split('|||')]
                    while len(parts) < len(sub_texts):
                        parts.append(sub_texts[len(parts)])
                    for idx, orig, trans in zip(sub_idx, sub_texts, parts):
                        british = apply_british_dictionary(trans)
                        translate_cache[orig] = british
                        results[idx] = british
                        stats['translated'] += 1
                    time.sleep(BATCH_DELAY)
                except Exception as e:
                    print(f'   ⚠️  Error: {e}')
                    for idx, orig in zip(sub_idx, sub_texts):
                        results[idx] = orig
                    stats['errors'] += 1
            continue

        # Proses normal
        try:
            from deep_translator import GoogleTranslator
            translated_combined = GoogleTranslator(source='id', target='en').translate(combined)
            parts = [p.strip() for p in translated_combined.split('|||')]

            while len(parts) < len(batch_texts):
                parts.append(batch_texts[len(parts)])

            for idx, orig, trans in zip(indices, batch_texts, parts):
                british = apply_british_dictionary(trans)
                translate_cache[orig] = british
                results[idx] = british
                stats['translated'] += 1

        except Exception as e:
            print(f'   ⚠️  Google Translate error: {e}')
            for idx, orig in zip(indices, batch_texts):
                results[idx] = orig
            stats['errors'] += 1

        time.sleep(BATCH_DELAY)

    return results


# ── XML helpers ────────────────────────────────────────────────────────────────
def get_paragraph_text(para_elem) -> str:
    return ''.join(
        (elem.text or '')
        for elem in para_elem.iter()
        if elem.tag == f'{{{W}}}t'
    )


def set_paragraph_text(para_elem, new_text: str):
    """Tulis teks baru ke paragraf tanpa mengubah formatting."""
    runs = [elem for elem in para_elem.iter() if elem.tag == f'{{{W}}}r']
    if not runs:
        return

    # Kumpulkan run yang berisi teks
    text_runs = []
    for run in runs:
        t_elems = run.findall(f'{{{W}}}t')
        if t_elems and any((t.text or '').strip() for t in t_elems):
            text_runs.append((run, t_elems))

    if not text_runs:
        return

    words = new_text.split()

    if len(text_runs) == 1:
        # Satu run → langsung assign
        _, t_elems = text_runs[0]
        t_elems[0].text = new_text
        if new_text != new_text.strip():
            t_elems[0].set('{http://www.w3.org/XML/1998/namespace}space', 'preserve')
        for t in t_elems[1:]:
            t.text = ''
    else:
        # Distribusi proporsional
        orig_lens   = []
        for _, t_elems in text_runs:
            orig = ''.join((t.text or '') for t in t_elems)
            orig_lens.append(max(1, len(orig.split())))
        total_orig  = sum(orig_lens)
        total_words = len(words)
        cursor      = 0

        for i, (_, t_elems) in enumerate(text_runs):
            if i == len(text_runs) - 1:
                chunk = words[cursor:]
            else:
                count  = max(1, round(orig_lens[i] / total_orig * total_words))
                chunk  = words[cursor : cursor + count]
                cursor += count

            chunk_text = ' '.join(chunk)
            t_elems[0].text = chunk_text
            if chunk_text != chunk_text.strip():
                t_elems[0].set('{http://www.w3.org/XML/1998/namespace}space', 'preserve')
            for t in t_elems[1:]:
                t.text = ''


def process_xml_part(xml_content: bytes) -> bytes:
    """Proses satu XML part: ekstrak paragraf → translate → tulis kembali."""
    try:
        tree = etree.fromstring(xml_content)
    except etree.XMLSyntaxError:
        return xml_content

    paragraphs  = tree.findall(f'.//{{{W}}}p')
    orig_texts  = [get_paragraph_text(p) for p in paragraphs]

    # Terjemahkan semua paragraf dalam batch
    translated  = google_translate_paragraphs(orig_texts)

    # Tulis kembali ke XML
    for para, new_text in zip(paragraphs, translated):
        if new_text and new_text != get_paragraph_text(para):
            set_paragraph_text(para, new_text)

    return etree.tostring(tree, xml_declaration=True, encoding='UTF-8', standalone=True)


# print('✅ Fungsi XML & Translate siap!')

TRANSLATABLE_PARTS = [
    'word/document.xml',
    'word/footnotes.xml',
    'word/endnotes.xml',
    'word/comments.xml',
]


def translate_docx_v3(input_path: str, output_path: str, custom_words: dict[str, str] | None = None):
    global stats
    stats = {'translated': 0, 'cached': 0, 'skipped': 0, 'errors': 0}
    translate_cache.clear()
    if custom_words:
        AM_TO_BR.update(custom_words)
    start_total = time.time()

    print('=' * 60)
    print(f'📄 Input  : {input_path}')
    print(f'📝 Output : {output_path}')
    print('=' * 60)

    tmp_dir  = tempfile.mkdtemp()
    try:
        tmp_docx = os.path.join(tmp_dir, 'working.docx')
        shutil.copy2(input_path, tmp_docx)

        with zipfile.ZipFile(tmp_docx, 'r') as z:
            all_names = z.namelist()

        # Tambahkan header & footer yang ditemukan dinamis
        parts_to_process = list(TRANSLATABLE_PARTS)
        for name in all_names:
            if name.startswith('word/') and name.endswith('.xml'):
                base = os.path.basename(name)
                if (base.startswith('header') or base.startswith('footer')) \
                   and name not in parts_to_process:
                    parts_to_process.append(name)

        parts_found = [p for p in parts_to_process if p in all_names]
        print(f'\n🔍 Bagian diproses: {parts_found}\n')

        translated_parts = {}

        for part_name in parts_found:
            t0 = time.time()
            print(f'⚡ {part_name} ...', end=' ', flush=True)

            with zipfile.ZipFile(tmp_docx, 'r') as z:
                xml_content = z.read(part_name)

            new_xml = process_xml_part(xml_content)
            translated_parts[part_name] = new_xml

            print(f'✅ {time.time()-t0:.1f}s')

        # Tulis output DOCX
        print('\n📦 Menyusun file output...')
        tmp_output = os.path.join(tmp_dir, 'output.docx')

        with zipfile.ZipFile(tmp_docx, 'r') as zin:
            with zipfile.ZipFile(tmp_output, 'w', compression=zipfile.ZIP_DEFLATED) as zout:
                for item in zin.infolist():
                    if item.filename in translated_parts:
                        zout.writestr(item, translated_parts[item.filename])
                    else:
                        zout.writestr(item, zin.read(item.filename))

        shutil.copy2(tmp_output, output_path)
    finally:
        shutil.rmtree(tmp_dir, ignore_errors=True)

    elapsed = time.time() - start_total
    print(f'\n' + '=' * 60)
    print(f'📊 RINGKASAN')
    print(f'=' * 60)
    print(f'   ⚡ Diterjemahkan (Google) : {stats["translated"]} paragraf')
    print(f'   ♻️  Dari cache             : {stats["cached"]}')
    print(f'   ⏭️  Dilewati               : {stats["skipped"]}')
    print(f'   ❌ Error                  : {stats["errors"]}')
    print(f'   ⏱️  Total waktu            : {elapsed:.1f} detik ({elapsed/60:.1f} menit)')
    print(f'   📁 Output                 : {output_path}')
    print(f'=' * 60)

    return {
        'translated': stats['translated'],
        'cached': stats['cached'],
        'skipped': stats['skipped'],
        'errors': stats['errors'],
        'elapsed_seconds': elapsed,
        'output_path': output_path,
    }


def parse_custom_words(raw_text: str) -> dict[str, str]:
    """Parse lines like American=British or American:British."""
    custom = {}
    for line in (raw_text or '').splitlines():
        line = line.strip()
        if not line or line.startswith('#'):
            continue
        if '=' in line:
            left, right = line.split('=', 1)
        elif ':' in line:
            left, right = line.split(':', 1)
        else:
            continue
        left = left.strip()
        right = right.strip()
        if not left or not right:
            continue
        pattern = rf'\b{re.escape(left)}\b'
        custom[pattern] = right
    return custom
