#!/usr/bin/env python3
"""
Clasificador de archivos XML
Organiza XMLs por tipo: Nómina, Gasto (CFDI), o Vacíos/No reconocidos
"""

import os
import sys
import json
import shutil
import zipfile
from xml_utils import IssueTracker, load_xml_root, find_first, print_progress

# Namespaces comunes
NAMESPACES_CFDI_40 = {
    "cfdi": "http://www.sat.gob.mx/cfd/4",
    "tfd": "http://www.sat.gob.mx/TimbreFiscalDigital",
}

NAMESPACES_CFDI_33 = {
    "cfdi": "http://www.sat.gob.mx/cfd/3",
    "tfd": "http://www.sat.gob.mx/TimbreFiscalDigital",
}

NAMESPACES_NOMINA = {
    "cfdi": "http://www.sat.gob.mx/cfd/3",
    "nomina12": "http://www.sat.gob.mx/nomina12",
}


def detect_xml_type(filepath: str, tracker: IssueTracker) -> str:
    """
    Detecta el tipo de XML: 'nomina', 'gasto', o 'vacio'

    Args:
        filepath: Ruta al archivo XML
        tracker: IssueTracker para registrar problemas

    Returns:
        String con el tipo: 'nomina', 'gasto', 'vacio'
    """
    root = load_xml_root(filepath, tracker)
    if root is None:
        return 'vacio'

    # Detectar Nómina (buscar complemento Nomina12)
    nomina_elem = find_first(root, ".//nomina12:Nomina", NAMESPACES_NOMINA)
    if nomina_elem is not None:
        return 'nomina'

    # Detectar CFDI 4.0
    comprobante_40 = find_first(root, ".//cfdi:Comprobante", NAMESPACES_CFDI_40)
    if comprobante_40 is not None:
        return 'gasto'

    # Detectar CFDI 3.3
    comprobante_33 = find_first(root, ".//cfdi:Comprobante", NAMESPACES_CFDI_33)
    if comprobante_33 is not None:
        # Verificar si tiene complemento de nómina aunque ya buscamos arriba
        # En caso de CFDIs raros que sean nómina pero no tengan el namespace
        return 'gasto'

    # Si el tag raíz es Comprobante (sin namespace prefix), también es CFDI
    if root.tag.endswith('Comprobante') or 'Comprobante' in root.tag:
        return 'gasto'

    # No reconocido
    tracker.warn(f"Archivo '{os.path.basename(filepath)}' no reconocido como Nómina ni CFDI")
    return 'vacio'


def clasificar_archivos(workdir: str, tracker: IssueTracker) -> dict:
    """
    Clasifica todos los archivos XML en el directorio

    Args:
        workdir: Directorio con los XMLs a clasificar
        tracker: IssueTracker para registrar problemas

    Returns:
        Dict con estadísticas y path del ZIP
    """
    # Crear subdirectorios
    nomina_dir = os.path.join(workdir, 'Nomina')
    gasto_dir = os.path.join(workdir, 'Gasto')
    vacios_dir = os.path.join(workdir, 'Vacios')

    os.makedirs(nomina_dir, exist_ok=True)
    os.makedirs(gasto_dir, exist_ok=True)
    os.makedirs(vacios_dir, exist_ok=True)

    # Contadores
    stats = {
        'nomina': 0,
        'gasto': 0,
        'vacios': 0,
        'total': 0
    }

    # Obtener lista de archivos XML
    xml_files = [f for f in os.listdir(workdir) if f.lower().endswith('.xml')]

    if not xml_files:
        tracker.error("No se encontraron archivos XML en el directorio")
        return stats

    print_progress(f"Clasificando {len(xml_files)} archivo(s) XML...")

    # Clasificar cada archivo
    for filename in xml_files:
        filepath = os.path.join(workdir, filename)

        # Detectar tipo
        xml_type = detect_xml_type(filepath, tracker)
        stats['total'] += 1

        # Mover a carpeta correspondiente
        if xml_type == 'nomina':
            dest = os.path.join(nomina_dir, filename)
            stats['nomina'] += 1
        elif xml_type == 'gasto':
            dest = os.path.join(gasto_dir, filename)
            stats['gasto'] += 1
        else:  # vacio
            dest = os.path.join(vacios_dir, filename)
            stats['vacios'] += 1

        try:
            shutil.copy2(filepath, dest)
            print_progress(f"✓ {filename} → {xml_type.capitalize()}")
        except Exception as e:
            tracker.error(f"No se pudo copiar '{filename}': {e}")

    print_progress(f"\nClasificación completada:")
    print_progress(f"  - Nómina: {stats['nomina']}")
    print_progress(f"  - Gasto: {stats['gasto']}")
    print_progress(f"  - Vacíos/No reconocidos: {stats['vacios']}")

    # Crear archivo ZIP
    zip_filename = f"XML_Clasificados.zip"
    zip_path = os.path.join(workdir, zip_filename)

    print_progress(f"\nCreando archivo ZIP...")

    try:
        with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
            # Agregar archivos de cada carpeta al ZIP
            for folder_name in ['Nomina', 'Gasto', 'Vacios']:
                folder_path = os.path.join(workdir, folder_name)
                files_in_folder = os.listdir(folder_path)

                if not files_in_folder:
                    # Crear carpeta vacía en el ZIP
                    zipf.writestr(f"{folder_name}/", '')
                else:
                    for filename in files_in_folder:
                        file_path = os.path.join(folder_path, filename)
                        arcname = os.path.join(folder_name, filename)
                        zipf.write(file_path, arcname)

        print_progress(f"✓ ZIP creado: {zip_filename}")

    except Exception as e:
        tracker.fatal(f"Error al crear ZIP: {e}")
        return stats

    return {'stats': stats, 'zip_path': zip_path}


def main():
    if len(sys.argv) < 2:
        print("ERROR: Falta el directorio de trabajo", file=sys.stderr)
        sys.exit(2)

    workdir = sys.argv[1]

    if not os.path.isdir(workdir):
        print(f"ERROR: '{workdir}' no es un directorio válido", file=sys.stderr)
        sys.exit(2)

    tracker = IssueTracker()

    try:
        result = clasificar_archivos(workdir, tracker)

        # Reportar problemas
        tracker.report()

        # Output JSON con path y stats
        if 'zip_path' in result:
            output = {
                'path': result['zip_path'],
                'stats': result['stats']
            }
            print(json.dumps(output))
        else:
            # Si no se pudo crear el ZIP, solo devolver stats
            print(json.dumps({'stats': result}))

        sys.exit(tracker.exit_code)

    except Exception as e:
        print(f"FATAL: Error inesperado: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc(file=sys.stderr)
        sys.exit(2)


if __name__ == "__main__":
    main()
