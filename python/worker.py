from __future__ import annotations

import argparse
import base64
import json
import sys
from pathlib import Path

from translator_core import (
    apply_british_dictionary,
    google_translate_paragraphs,
    parse_custom_words,
    translate_docx_v3,
)


def emit(payload: dict, status: int = 0) -> None:
    print(json.dumps(payload, ensure_ascii=False))
    raise SystemExit(status)


def merge_nearby_texts(ocr_results, horizontal_threshold=50):
    if not ocr_results:
        return ocr_results
    merged_results, used_indices = [], set()
    for i, (bbox, text, confidence) in enumerate(ocr_results):
        if i in used_indices:
            continue
        xs, ys = [p[0] for p in bbox], [p[1] for p in bbox]
        x_max, y_min, y_max = max(xs), min(ys), max(ys)
        merged_text, merged_bbox, merged_confidence = text, bbox, confidence
        for j in range(i + 1, len(ocr_results)):
            if j in used_indices:
                continue
            next_bbox, next_text, next_confidence = ocr_results[j]
            next_xs, next_ys = [p[0] for p in next_bbox], [p[1] for p in next_bbox]
            if not (y_max < min(next_ys) or y_min > max(next_ys)) and abs(min(next_xs) - x_max) <= horizontal_threshold:
                merged_text += next_text
                all_xs = [p[0] for p in merged_bbox] + next_xs
                all_ys = [p[1] for p in merged_bbox] + next_ys
                merged_bbox = [[min(all_xs), min(all_ys)], [max(all_xs), min(all_ys)], [max(all_xs), max(all_ys)], [min(all_xs), max(all_ys)]]
                x_max, y_min, y_max = max(all_xs), min(all_ys), max(all_ys)
                merged_confidence = (merged_confidence + next_confidence) / 2
                used_indices.add(j)
        merged_results.append((merged_bbox, merged_text, merged_confidence))
        used_indices.add(i)
    return merged_results


def overlay_text_on_image(image_path: str, ocr_results: list, translated_texts: list):
    from PIL import Image, ImageDraw, ImageFont

    image = Image.open(image_path).convert('RGB')
    draw = ImageDraw.Draw(image)
    try:
        font = ImageFont.truetype('arial.ttf', 20)
    except Exception:
        font = ImageFont.load_default()
    for (bbox, _, _), translated in zip(ocr_results, translated_texts):
        if not translated or not bbox:
            continue
        xs, ys = [p[0] for p in bbox], [p[1] for p in bbox]
        draw.rectangle([max(0, int(min(xs)) - 2), max(0, int(min(ys)) - 2), min(image.width, int(max(xs)) + 2), min(image.height, int(max(ys)) + 2)], fill='white')
    for (bbox, _, _), translated in zip(ocr_results, translated_texts):
        if not translated or not bbox:
            continue
        xs, ys = [p[0] for p in bbox], [p[1] for p in bbox]
        draw.text((int((min(xs) + max(xs)) / 2), int((min(ys) + max(ys)) / 2)), translated, fill='black', font=font, anchor='mm')
    return image


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument('operation', choices=['docx', 'ocr'])
    parser.add_argument('--input', required=True)
    parser.add_argument('--output', required=True)
    parser.add_argument('--profile', default='academic')
    parser.add_argument('--custom-words', default='')
    parser.add_argument('--source-language', default='auto', choices=['auto', 'id', 'en-US', 'en-GB'])
    parser.add_argument('--target-language', default='en-GB', choices=['id', 'en-US', 'en-GB'])
    args = parser.parse_args()
    custom_raw = base64.b64decode(args.custom_words).decode('utf-8') if args.custom_words else ''
    custom_words = parse_custom_words(custom_raw)

    if args.operation == 'docx':
        summary = translate_docx_v3(
            args.input,
            args.output,
            custom_words=custom_words,
            profile=args.profile,
            source_language=args.source_language,
            target_language=args.target_language,
        )
        emit({'ok': True, 'summary': summary})

    try:
        import easyocr
    except ImportError:
        emit({'ok': False, 'error': 'OCR tidak tersedia. Pastikan easyocr telah diinstal.'}, 1)
    reader = easyocr.Reader(['id', 'en'], gpu=False)
    results = merge_nearby_texts(reader.readtext(args.input), horizontal_threshold=50)
    if not results:
        emit({'ok': False, 'error': 'Tidak ada teks yang terdeteksi dalam gambar.'}, 1)
    extracted = [item[1] for item in results]
    translated = google_translate_paragraphs(extracted, profile=args.profile, custom_words=custom_words)
    translated = [apply_british_dictionary(line, custom_words) for line in translated]
    overlay_text_on_image(args.input, results, translated).save(args.output, 'PNG')
    emit({'ok': True})


if __name__ == '__main__':
    try:
        main()
    except SystemExit:
        raise
    except Exception as exc:
        emit({'ok': False, 'error': str(exc)}, 1)
