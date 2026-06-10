"""Tests for the confidence scoring module."""

from app.confidence import (
    pattern_score_cp,
    pattern_score_curp,
    pattern_score_id_ine,
    pattern_score_name,
    pattern_score_seccion,
    score_field,
)


class TestScoreField:
    def test_none_value(self):
        result = score_field(None, 0.5, 0.5, 0.5)
        assert result.value is None
        assert result.confidence == 0.0
        assert result.requires_review is True

    def test_high_confidence(self):
        result = score_field("JUAN", 1.0, 0.9, 1.0)
        assert result.confidence >= 0.85
        assert result.requires_review is False

    def test_low_confidence(self):
        result = score_field("?", 0.2, 0.2, 0.2)
        assert result.confidence < 0.65
        assert result.requires_review is True


class TestPatternScores:
    def test_name_good(self):
        assert pattern_score_name("JUAN CARLOS") >= 0.8

    def test_name_empty(self):
        assert pattern_score_name("") == 0.0

    def test_curp_valid(self):
        assert pattern_score_curp("PELJ000101HDFRPNA1") == 1.0

    def test_curp_invalid(self):
        assert pattern_score_curp("XXXX") == 0.2

    def test_id_ine_18(self):
        assert pattern_score_id_ine("A" * 18) == 1.0

    def test_id_ine_17(self):
        assert pattern_score_id_ine("A" * 17) == 0.8

    def test_seccion_good(self):
        assert pattern_score_seccion("0234") == 1.0

    def test_cp_good(self):
        assert pattern_score_cp("06600") == 1.0

    def test_cp_bad(self):
        assert pattern_score_cp("123") == 0.2
