import os
import sys
import xml.etree.ElementTree as ET
from typing import Any, Dict, List, Optional, Tuple


class IssueTracker:
    """Collects warnings/errors/fatals and derives an exit code."""

    def __init__(self) -> None:
        self.warnings: List[str] = []
        self.errors: List[str] = []
        self.fatals: List[str] = []

    def warn(self, message: str) -> None:
        self.warnings.append(message)

    def error(self, message: str) -> None:
        self.errors.append(message)

    def fatal(self, message: str) -> None:
        self.fatals.append(message)

    @property
    def exit_code(self) -> int:
        if self.fatals:
            return 2
        if self.errors:
            return 1
        return 0

    def report(self, prefix: str = "") -> None:
        label = f"{prefix}: " if prefix else ""
        for msg in self.warnings:
            print(f"{label}WARNING: {msg}", file=sys.stderr)
        for msg in self.errors:
            print(f"{label}ERROR: {msg}", file=sys.stderr)
        for msg in self.fatals:
            print(f"{label}FATAL: {msg}", file=sys.stderr)


def normalize_text(value: Any) -> Optional[str]:
    """Return stripped utf-8-safe text; blank -> None."""
    if value is None:
        return None
    try:
        text = str(value)
    except Exception:
        return None
    text = text.encode("utf-8", "replace").decode("utf-8", "replace").strip()
    return text if text else None


def to_float(value: Any, default: float = 0.0, tracker: Optional[IssueTracker] = None, context: str = "") -> float:
    """Convert to float safely, log warning on failure."""
    if value is None or value == "":
        return default
    try:
        return float(value)
    except Exception:
        if tracker is not None:
            tracker.warn(f"No se pudo convertir a número '{value}' en {context}") if context else tracker.warn(
                f"No se pudo convertir a número '{value}'"
            )
        return default


def strip_namespace(tag: str) -> str:
    if "}" in tag:
        return tag.split("}", 1)[1]
    return tag


def load_xml_root(path: str, tracker: IssueTracker) -> Optional[ET.Element]:
    """Load XML defensively and strip namespaces where needed."""
    if not os.path.exists(path):
        tracker.fatal(f"Archivo no encontrado: {path}")
        return None
    try:
        tree = ET.parse(path)
        root = tree.getroot()
    except Exception as exc:
        tracker.fatal(f"No se pudo leer/parsing XML '{os.path.basename(path)}': {exc}")
        return None
    return root


def find_first(root: ET.Element, xpath: str, namespaces: Dict[str, str]) -> Optional[ET.Element]:
    try:
        return root.find(xpath, namespaces)
    except Exception:
        return None


def find_all(root: ET.Element, xpath: str, namespaces: Dict[str, str]) -> List[ET.Element]:
    try:
        return root.findall(xpath, namespaces)
    except Exception:
        return []


def get_attr(element: Optional[ET.Element], attr: str, default: Optional[str] = None) -> Optional[str]:
    if element is None:
        return default
    return normalize_text(element.attrib.get(attr, default))


def safe_attrib(element: Optional[ET.Element]) -> Dict[str, str]:
    return {k: normalize_text(v) or "" for k, v in (element.attrib.items() if element is not None else [])}


def print_progress(message: str) -> None:
    print(message, file=sys.stderr)
