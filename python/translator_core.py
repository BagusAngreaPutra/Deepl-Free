from __future__ import annotations

import os
import re
import shutil
import tempfile
import time
import zipfile
from dataclasses import dataclass
from typing import Dict, List, Tuple

from lxml import etree

from translator_core_legacy import AM_TO_BR, apply_british_dictionary as legacy_apply_british_dictionary

W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
TRANSLATABLE_PARTS = [
    'word/document.xml',
    'word/footnotes.xml',
    'word/endnotes.xml',
    'word/comments.xml',
]

# Google Translate accepts roughly 5,000 characters per request.  Keeping a
# little headroom lets us send substantially fewer requests without hitting
# that limit.  The old values made medium-sized documents take long enough for
# the web worker/proxy to close the connection (shown by fetch as
# "Failed to fetch").
BATCH_SIZE = 20
MAX_BATCH_CHARS = 4500
BATCH_DELAY = 0.05
SEPARATOR = '\nZXQSEP001ZXQ\n'

translate_cache: Dict[str, str] = {}
stats = {'translated': 0, 'cached': 0, 'skipped': 0, 'errors': 0}


@dataclass(frozen=True)
class Profile:
    key: str
    label: str
    description: str


PROFILES: Dict[str, Profile] = {
    'standard': Profile(
        key='standard',
        label='Standard British English',
        description='General British spelling and light polishing.',
    ),
    'academic': Profile(
        key='academic',
        label='British Academic English',
        description='Academic phrasing and cleaner university-style English.',
    ),
    'edu_academic': Profile(
        key='edu_academic',
        label='British Academic English for Course Documents',
        description='Optimised for CLO/Sub-CLO, lesson plans, tasks, and assessment rubrics.',
    ),
}


SOURCE_GLOSSARY_BASE: List[Tuple[str, str]] = [
    (r'\bkontrak kuliah\b', 'Course Agreement'),
    (r'\brencana pembelajaran semester\b', 'Semester Learning Plan'),
    (r'\bcapaian pembelajaran lulusan\b', 'Graduate Learning Outcomes'),
    (r'\bcapaian pembelajaran mata kuliah\b', 'Course Learning Outcomes'),
    (r'\bsub[- ]?capaian pembelajaran mata kuliah\b', 'Sub-course learning outcomes'),
    (r'\bcapaian pembelajaran\b', 'learning outcomes'),
    (r'\brubrik penilaian\b', 'Assessment Rubric'),
    (r'\bteknik penilaian\b', 'Assessment Method'),
    (r'\bpenugasan\b', 'assignment'),
    (r'\bbukti pengumpulan\b', 'evidence of submission'),
    (r'\bbukti penugasan\b', 'assessment evidence'),
    (r'\bpasangan minimal\b', 'minimal pairs'),
    (r'\bfitur artikulasi\b', 'articulatory features'),
    (r'\bfonetik\b', 'phonetics'),
    (r'\bfonologi\b', 'phonology'),
    (r'\bmorfologi\b', 'morphology'),
    (r'\bsintaksis\b', 'syntax'),
    (r'\bsemantik\b', 'semantics'),
    (r'\bpragmatik\b', 'pragmatics'),
    (r'\bsosiolinguistik\b', 'sociolinguistics'),
]

SOURCE_GLOSSARY_PROFILE: Dict[str, List[Tuple[str, str]]] = {
    'standard': SOURCE_GLOSSARY_BASE[:6],
    'academic': SOURCE_GLOSSARY_BASE[:12],
    'edu_academic': SOURCE_GLOSSARY_BASE,
}


START_VERB_FIXES = {
    'Shows': 'Explain',
    'Show': 'Explain',
    'Demonstrates': 'Demonstrate',
    'Demonstrating': 'Demonstrate',
    'Explores': 'Explore',
    'Exploring': 'Explore',
    'Compares': 'Compare',
    'Comparing': 'Compare',
    'Analyses': 'Analyse',
    'Analysing': 'Analyse',
    'Analyzes': 'Analyse',
    'Analyzing': 'Analyse',
    'Identifies': 'Identify',
    'Identifying': 'Identify',
    'Recognises': 'Recognise',
    'Recognising': 'Recognise',
    'Recognizes': 'Recognise',
    'Recognizing': 'Recognise',
    'Categorises': 'Categorise',
    'Categorising': 'Categorise',
    'Solving': 'Solve',
    'Discussing': 'Discuss',
    'Evaluating': 'Evaluate',
    'Evaluates': 'Evaluate',
    'Describes': 'Describe',
    'Describing': 'Describe',
    'Explains': 'Explain',
    'Explaining': 'Explain',
}


ACADEMIC_REPLACEMENTS: List[Tuple[str, str]] = [
    (r'\bsmallest pairs\b', 'minimal pairs'),
    (r'\barticulation features\b', 'articulatory features'),
    (r'\bcopy basic English sounds\b', 'transcribe basic English sounds'),
    (r'\busing IPA\b', 'using the IPA'),
    (r'\buse IPA\b', 'use the IPA'),
    (r'\bTuition Contract\b', 'Course Agreement'),
    (r'\bBill\s*\(proof\)', 'Evidence of submission'),
    (r'\bliveliness of answering directly\b', 'active participation and responsiveness during discussion'),
    (r'\bactive liveliness\b', 'active participation'),
    (r'\banswering directly\b', 'responding directly'),
    (r'\bthe smallest sound pair\b', 'the minimal pair'),
    (r'\bminimal pair[s]? smallest\b', 'minimal pairs'),
    (r'\blanguage problems in society\b', 'language-related problems in society'),
    (r'\bsociolinguistic variations\b', 'sociolinguistic variation'),
    (r'\bwhilst\b', 'while'),
    (r'\bAmongst\b', 'Among'),
    (r'\bamongst\b', 'among'),
    (r'\blicenced\b', 'licensed'),
    (r'\blicencing\b', 'licensing'),
    (r'\bprogramme\b(?=\s+code\b)', 'program'),
    (r'\bprogramme\b(?=\s+file\b)', 'program'),
]


EDU_SPECIFIC_REPLACEMENTS: List[Tuple[str, str]] = [
    (
        r'(?i)^\s*recogni[sz]e articulation features and copy basic English sounds using (?:the )?IPA\.?$',
        'Recognise articulatory features and transcribe basic English sounds using the IPA.',
    ),
    (
        r'(?i)^\s*show[s]? how meaning is derived from words and sentence structure\.?$',
        'Explain how meaning is derived from words and sentence structure.',
    ),
    (
        r'(?i)^\s*solve language(?:-related)? problems in society,? sociolinguistic variation[s]? and (?:its|their) implications\.?$',
        'Solve language-related problems in society and explain sociolinguistic variation and its implications.',
    ),
    (
        r'(?i)^\s*solve language(?:-related)? problems in society,? sociolinguistic variation and their implications\.?$',
        'Solve language-related problems in society and explain sociolinguistic variation and its implications.',
    ),
    (
        r'(?i)^\s*create a\s*(\d+)\s*[-–]?\s*(\d+)\s*(?:minutes?|minute)?\s+educational podcast\s+which\b',
        r'Create a \1–\2-minute educational podcast that',
    ),
    (
        r'(?i)^\s*create a\s*(\d+)\s*[-–]?\s*educational podcast\s*(\d+)\s*minutes?\s+that\b',
        r'Create a \1–\2-minute educational podcast that',
    ),
    (
        r'(?i)^\s*create a\s*(\d+)\s*[-–]?\s*educational podcast\s*(\d+)\s*minutes?\s+which\b',
        r'Create a \1–\2-minute educational podcast that',
    ),
    (
        r'(?i)^\s*create a\s*(\d+)\s*[-–]\s*educational podcast\s*(\d+)\s*minutes\s+which\b',
        r'Create a \1–\2-minute educational podcast that',
    ),
    (
        r'(?i)^\s*create a\s*(\d+)\s*to\s*(\d+)\s*minute educational podcast which\b',
        r'Create a \1–\2-minute educational podcast that',
    ),
    (
        r'(?i)^\s*able to analyse\b',
        'Analyse',
    ),
    (
        r'(?i)^\s*able to identify\b',
        'Identify',
    ),
    (
        r'(?i)^\s*able to explain\b',
        'Explain',
    ),
]


def parse_custom_words(raw_text: str) -> Dict[str, str]:
    custom: Dict[str, str] = {}
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
        custom[rf'\b{re.escape(left)}\b'] = right
    return custom


def apply_british_dictionary(text: str, custom_words: Dict[str, str] | None = None) -> str:
    if custom_words:
        original = dict(AM_TO_BR)
        try:
            AM_TO_BR.update(custom_words)
            text = legacy_apply_british_dictionary(text)
        finally:
            AM_TO_BR.clear()
            AM_TO_BR.update(original)
    else:
        text = legacy_apply_british_dictionary(text)

    for pattern, replacement in ACADEMIC_REPLACEMENTS:
        text = re.sub(pattern, replacement, text, flags=re.IGNORECASE)

    # Normalise spacing/punctuation after replacements.
    text = re.sub(r'\s+([,.;:!?])', r'\1', text)
    text = re.sub(r'([({\[])\s+', r'\1', text)
    text = re.sub(r'\s+([)}\]])', r'\1', text)
    text = re.sub(r'\s{2,}', ' ', text).strip()
    return text


def is_translatable(text: str) -> bool:
    text = (text or '').strip()
    if not text or len(text) < 2:
        return False
    if re.fullmatch(r'[\d\s\.,;:!?@#$%^&*()\-_=+\[\]{}|<>/\\"\'"…•·°±×÷≤≥≠≈∞%]+', text):
        return False
    if re.match(r'https?://', text):
        return False
    if '@' in text and '.' in text and ' ' not in text:
        return False
    return bool(re.search(r'[A-Za-zÀ-ÿ]', text))


def protect_terms(text: str, profile: str) -> Tuple[str, Dict[str, str]]:
    glossary = SOURCE_GLOSSARY_PROFILE.get(profile, SOURCE_GLOSSARY_PROFILE['academic'])
    replacements: Dict[str, str] = {}
    protected_text = text
    index = 0

    for pattern, replacement in glossary:
        def _repl(match: re.Match[str]) -> str:
            nonlocal index
            token = f'ZXQTERM{index:03d}ZXQ'
            replacements[token] = replacement
            index += 1
            return token

        protected_text = re.sub(pattern, _repl, protected_text, flags=re.IGNORECASE)

    return protected_text, replacements


def restore_terms(text: str, replacements: Dict[str, str]) -> str:
    for token, replacement in replacements.items():
        text = text.replace(token, replacement)
    return text


def normalise_lo_lead(text: str) -> str:
    stripped = text.strip()
    if not stripped:
        return text

    # Short structured statements are likely CLO / rubric items.
    if len(stripped) > 220:
        return text

    stripped = re.sub(r'^(Students?|Learners?)\s+are\s+able\s+to\s+', '', stripped, flags=re.IGNORECASE)
    stripped = re.sub(r'^Able to\s+', '', stripped, flags=re.IGNORECASE)

    for src, tgt in START_VERB_FIXES.items():
        if stripped.startswith(src + ' '):
            return tgt + stripped[len(src):]

    return stripped


def polish_sentence_boundaries(text: str) -> str:
    text = re.sub(r'\s+([,.;:!?])', r'\1', text)
    text = re.sub(r'([,.;:!?])(\S)', r'\1 \2', text)
    text = re.sub(r'\s{2,}', ' ', text).strip()
    return text


def academic_polish(text: str, profile: str = 'academic') -> str:
    text = polish_sentence_boundaries(text)

    if profile in {'academic', 'edu_academic'}:
        text = re.sub(r'\bthe IPA\b', 'the IPA', text)
        text = re.sub(r'\b([0-9]+)\s*[-–]\s*([0-9]+)\s*(minutes?|minute|hours?|hour)\b', r'\1–\2-\3', text)
        text = re.sub(r'\b([0-9]+)\s+to\s+([0-9]+)\s*(minutes?|minute|hours?|hour)\b', r'\1–\2 \3', text)
        text = re.sub(r'\b([0-9]+)\s*[-–]\s*([0-9]+)-minutes\b', r'\1–\2-minute', text)
        text = re.sub(r'\b([0-9]+)\s*[-–]\s*([0-9]+)-minute\b', r'\1–\2-minute', text)
        text = re.sub(r'\b([0-9]+)\s*[-–]\s*([0-9]+)\s+minute\b', r'\1–\2-minute', text)
        text = re.sub(r'\b([0-9]+)\s*[-–]\s*([0-9]+)\s+minutes\b', r'\1–\2-minute', text)
        text = re.sub(r'\bwhich discusses and analyses\b', 'that discusses and analyses', text, flags=re.IGNORECASE)
        text = re.sub(r'\bwhich discusses\b', 'that discusses', text, flags=re.IGNORECASE)
        text = re.sub(r'\bwhich explains\b', 'that explains', text, flags=re.IGNORECASE)

    if profile == 'edu_academic':
        for pattern, replacement in EDU_SPECIFIC_REPLACEMENTS:
            text = re.sub(pattern, replacement, text, flags=re.IGNORECASE)
        text = normalise_lo_lead(text)
        for pattern, replacement in EDU_SPECIFIC_REPLACEMENTS:
            text = re.sub(pattern, replacement, text, flags=re.IGNORECASE)
        text = re.sub(
            r'(?i)\bminimal pairs? and articulatory features\b',
            'minimal pairs and articulatory features',
            text,
        )
        text = re.sub(
            r'(?i)\brespond directly in discussion\b',
            'respond appropriately during discussion',
            text,
        )

    if text and text[0].islower() and not text.startswith(('e.g.', 'i.e.')):
        text = text[0].upper() + text[1:]

    text = polish_sentence_boundaries(text)
    return text


def _get_translator():
    from deep_translator import GoogleTranslator
    return GoogleTranslator(source='auto', target='en')


def translate_batch(batch_texts: List[str]) -> List[str]:
    combined = SEPARATOR.join(batch_texts)
    translated = _get_translator().translate(combined)
    if SEPARATOR in translated:
        parts = translated.split(SEPARATOR)
    else:
        parts = translated.split('ZXQSEP001ZXQ')
    
    # Only strip newlines but preserve leading/trailing spaces from original context
    parts = [part.strip('\n\r\t') if part else '' for part in parts]
    while len(parts) < len(batch_texts):
        parts.append(batch_texts[len(parts)])
    return parts[:len(batch_texts)]


def google_translate_paragraphs(
    texts: List[str],
    profile: str = 'academic',
    custom_words: Dict[str, str] | None = None,
) -> List[str]:
    results = [''] * len(texts)
    to_translate: List[Tuple[int, str, Dict[str, str]]] = []

    for i, text in enumerate(texts):
        if text in translate_cache:
            results[i] = translate_cache[text]
            stats['cached'] += 1
            continue
        if not is_translatable(text):
            results[i] = text
            stats['skipped'] += 1
            continue

        protected_text, replacements = protect_terms(text, profile)
        to_translate.append((i, protected_text, replacements))

    cursor = 0
    while cursor < len(to_translate):
        batch: List[Tuple[int, str, Dict[str, str]]] = []
        total_chars = 0
        while cursor < len(to_translate) and len(batch) < BATCH_SIZE:
            item = to_translate[cursor]
            projected = total_chars + len(item[1]) + len(SEPARATOR)
            if batch and projected > MAX_BATCH_CHARS:
                break
            batch.append(item)
            total_chars = projected
            cursor += 1

        if not batch:
            batch = [to_translate[cursor]]
            cursor += 1

        indices = [item[0] for item in batch]
        batch_texts = [item[1] for item in batch]
        replacements_list = [item[2] for item in batch]

        try:
            parts = translate_batch(batch_texts)
            for idx, original_src, replacements, translated in zip(indices, batch_texts, replacements_list, parts):
                restored = restore_terms(translated, replacements)
                polished = apply_british_dictionary(restored, custom_words=custom_words)
                polished = academic_polish(polished, profile=profile)
                translate_cache[texts[idx]] = polished
                results[idx] = polished
                stats['translated'] += 1
        except Exception:
            # Fallback: translate paragraph by paragraph for better resilience.
            for idx, original_src, replacements in zip(indices, batch_texts, replacements_list):
                try:
                    translated = _get_translator().translate(original_src)
                    restored = restore_terms(translated, replacements)
                    polished = apply_british_dictionary(restored, custom_words=custom_words)
                    polished = academic_polish(polished, profile=profile)
                    translate_cache[texts[idx]] = polished
                    results[idx] = polished
                    stats['translated'] += 1
                except Exception:
                    results[idx] = texts[idx]
                    stats['errors'] += 1

        time.sleep(BATCH_DELAY)

    return results


def get_paragraph_text(para_elem) -> str:
    return ''.join(
        (elem.text or '')
        for elem in para_elem.iter()
        if elem.tag == f'{{{W}}}t'
    )


def set_paragraph_text(para_elem, new_text: str):
    runs = [elem for elem in para_elem.iter() if elem.tag == f'{{{W}}}r']
    if not runs:
        return

    text_runs = []
    for run in runs:
        t_elems = run.findall(f'{{{W}}}t')
        if t_elems and any((t.text or '').strip() for t in t_elems):
            text_runs.append((run, t_elems))

    if not text_runs:
        return

    if len(text_runs) == 1:
        # Single run: put all text in first text element
        _, t_elems = text_runs[0]
        t_elems[0].text = new_text
        if new_text != new_text.strip():
            t_elems[0].set('{http://www.w3.org/XML/1998/namespace}space', 'preserve')
        for t in t_elems[1:]:
            t.text = ''
        return

    # Multiple runs: put ALL translated text in the FIRST run to avoid
    # mid-word splits (e.g. "curr" + "iculum" or "a" + "nd").
    # Distributing by character proportion from the original language is
    # unreliable because translated text has different word boundaries.
    # Subsequent runs are cleared but kept in the XML so their formatting
    # marks (bold, italic, font size, etc.) remain untouched.
    _, first_t_elems = text_runs[0]
    first_t_elems[0].text = new_text
    if new_text != new_text.strip():
        first_t_elems[0].set('{http://www.w3.org/XML/1998/namespace}space', 'preserve')
    for t in first_t_elems[1:]:
        t.text = ''

    # Clear text content from all subsequent runs (keep run element intact)
    for _, t_elems in text_runs[1:]:
        for t in t_elems:
            t.text = ''


def process_xml_part(
    xml_content: bytes,
    profile: str = 'academic',
    custom_words: Dict[str, str] | None = None,
) -> bytes:
    try:
        tree = etree.fromstring(xml_content)
    except etree.XMLSyntaxError:
        return xml_content

    paragraphs = tree.findall(f'.//{{{W}}}p')
    orig_texts = [get_paragraph_text(p) for p in paragraphs]
    translated = google_translate_paragraphs(orig_texts, profile=profile, custom_words=custom_words)

    for para, new_text in zip(paragraphs, translated):
        if new_text and new_text != get_paragraph_text(para):
            set_paragraph_text(para, new_text)

    return etree.tostring(tree, xml_declaration=True, encoding='UTF-8', standalone=True)


def translate_docx_v3(
    input_path: str,
    output_path: str,
    custom_words: Dict[str, str] | None = None,
    profile: str = 'edu_academic',
):
    global stats
    stats = {'translated': 0, 'cached': 0, 'skipped': 0, 'errors': 0}
    translate_cache.clear()
    profile = profile if profile in PROFILES else 'edu_academic'

    start_total = time.time()
    tmp_dir = tempfile.mkdtemp()
    try:
        tmp_docx = os.path.join(tmp_dir, 'working.docx')
        shutil.copy2(input_path, tmp_docx)

        with zipfile.ZipFile(tmp_docx, 'r') as z:
            all_names = z.namelist()

        parts_to_process = list(TRANSLATABLE_PARTS)
        for name in all_names:
            if name.startswith('word/') and name.endswith('.xml'):
                base = os.path.basename(name)
                if (base.startswith('header') or base.startswith('footer')) and name not in parts_to_process:
                    parts_to_process.append(name)

        parts_found = [p for p in parts_to_process if p in all_names]
        translated_parts = {}

        for part_name in parts_found:
            with zipfile.ZipFile(tmp_docx, 'r') as z:
                xml_content = z.read(part_name)
            translated_parts[part_name] = process_xml_part(
                xml_content,
                profile=profile,
                custom_words=custom_words,
            )

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
    return {
        'translated': stats['translated'],
        'cached': stats['cached'],
        'skipped': stats['skipped'],
        'errors': stats['errors'],
        'elapsed_seconds': elapsed,
        'output_path': output_path,
        'profile': profile,
    }
