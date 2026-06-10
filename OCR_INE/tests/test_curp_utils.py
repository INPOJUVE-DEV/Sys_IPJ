"""Tests for CURP validation and field derivation."""

from app.curp_utils import (
    extract_fecha_nacimiento,
    extract_sexo,
    find_curp_in_text,
    is_valid_curp,
)


class TestIsValidCurp:
    def test_valid_curp(self):
        assert is_valid_curp("PELJ000101HDFRPNA1")

    def test_valid_curp_female(self):
        assert is_valid_curp("GALA950315MDFLRN09")

    def test_invalid_too_short(self):
        assert not is_valid_curp("PELJ0001")

    def test_invalid_bad_date(self):
        assert not is_valid_curp("PELJ001301HDFRPNA1")  # month 13

    def test_invalid_bad_sex(self):
        assert not is_valid_curp("PELJ000101XDFRPNA1")  # X not valid

    def test_empty(self):
        assert not is_valid_curp("")


class TestExtractFechaNacimiento:
    def test_year_2000(self):
        assert extract_fecha_nacimiento("PELJ000101HDFRPNA1") == "2000-01-01"

    def test_year_1995(self):
        assert extract_fecha_nacimiento("GALA950315MDFLRN09") == "1995-03-15"

    def test_year_1969(self):
        assert extract_fecha_nacimiento("AAAA690101HDFRPNA1") is not None
        result = extract_fecha_nacimiento("AAAA690101HDFRPNA1")
        assert result is not None
        assert result.startswith("1969")

    def test_invalid_curp(self):
        assert extract_fecha_nacimiento("short") is None


class TestExtractSexo:
    def test_hombre(self):
        assert extract_sexo("PELJ000101HDFRPNA1") == "M"

    def test_mujer(self):
        assert extract_sexo("GALA950315MDFLRN09") == "F"

    def test_short(self):
        assert extract_sexo("ABC") is None


class TestFindCurpInText:
    def test_clean_curp(self):
        assert find_curp_in_text("PELJ000101HDFRPNA1") == "PELJ000101HDFRPNA1"

    def test_curp_in_noisy_text(self):
        text = "CURP PELJ000101HDFRPNA1 SOME OTHER TEXT"
        assert find_curp_in_text(text) == "PELJ000101HDFRPNA1"

    def test_no_curp(self):
        assert find_curp_in_text("NO CURP HERE 12345") is None
