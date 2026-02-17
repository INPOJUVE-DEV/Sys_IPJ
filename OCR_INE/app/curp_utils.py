"""CURP validation and field derivation (fecha_nacimiento, sexo)."""

from __future__ import annotations

import re
from datetime import date

# CURP regex — 18 alphanumeric characters, Mexican standard format
# Relaxed version: accepts any uppercase letters in consonant positions
# because OCR output may contain substitution errors.
CURP_REGEX = re.compile(
    r"^[A-Z][AEIOUX][A-Z]{2}"     # 4: apellido+nombre initials
    r"\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])"  # 6: YYMMDD
    r"[HM]"                        # 1: sexo
    r"[A-Z]{2}"                    # 2: entidad federativa
    r"[A-Z]{3}"                    # 3: consonantes internas (relaxed)
    r"[A-Z0-9]{2}$",              # 2: homoclave + dígito verificador
    re.IGNORECASE,
)


def is_valid_curp(curp: str) -> bool:
    """Check if a string matches the CURP format."""
    return bool(CURP_REGEX.match(curp.strip().upper()))


def extract_fecha_nacimiento(curp: str) -> str | None:
    """Extract birth date from CURP as YYYY-MM-DD string.

    CURP positions 4-9 encode YYMMDD.
    """
    curp = curp.strip().upper()
    if len(curp) < 10:
        return None

    try:
        yy = int(curp[4:6])
        mm = int(curp[6:8])
        dd = int(curp[8:10])

        # Century heuristic: 00-30 → 2000s, 31-99 → 1900s
        year = 2000 + yy if yy <= 30 else 1900 + yy

        birth = date(year, mm, dd)
        return birth.isoformat()
    except (ValueError, IndexError):
        return None


def extract_sexo(curp: str) -> str | None:
    """Extract sex from CURP (position 10): H=Hombre, M=Mujer.

    Maps to form values: H→M (masculino), M→F (femenino).
    """
    curp = curp.strip().upper()
    if len(curp) < 11:
        return None

    sexo_char = curp[10]
    if sexo_char == "H":
        return "M"  # Masculino
    elif sexo_char == "M":
        return "F"  # Femenino
    return None


def find_curp_in_text(text: str) -> str | None:
    """Search for a CURP pattern within OCR text."""
    text = text.upper().strip()

    # Strategy 1: split by whitespace and check individual tokens
    words = re.split(r"[\s,;:]+", text)
    for word in words:
        clean = re.sub(r"[^A-Z0-9]", "", word)
        if len(clean) == 18 and is_valid_curp(clean):
            return clean

    # Strategy 2: find all 18-char alphanumeric sequences in the raw text
    tokens = re.findall(r"[A-Z0-9]{18}", text)
    for token in tokens:
        if is_valid_curp(token):
            return token

    # Strategy 3: merge everything and look for sliding windows
    merged = re.sub(r"[^A-Z0-9]", "", text)
    for i in range(len(merged) - 17):
        candidate = merged[i:i + 18]
        if is_valid_curp(candidate):
            return candidate

    return None
