import os
import sys
import xml.etree.ElementTree as ET
import openpyxl
from openpyxl.styles import PatternFill, Font

# Colores para celdas
green_fill = PatternFill(start_color="C6EFCE", end_color="C6EFCE", fill_type="solid")
red_fill = PatternFill(start_color="FFC7CE", end_color="FFC7CE", fill_type="solid")
blue_fill = PatternFill(start_color="DDEBF7", end_color="DDEBF7", fill_type="solid")
header_fill = PatternFill(start_color="BDD7EE", end_color="BDD7EE", fill_type="solid")


def procesar_nomina_xml(directorio):
    wb = openpyxl.Workbook()
    ws = wb.active
    ws.title = "Perc_Deduc_Sub"

    # Encabezados
    ws.append(["Archivo", "Tipo", "TipoPercepcion/Deduccion/Subsidio", "Clave", "Concepto", "ImporteGravado", "ImporteExento", "ImporteTotal"])

    percepciones_catalogo = set()
    deducciones_catalogo = set()
    subsidios_catalogo = set()

    xml_files = [file for file in os.listdir(directorio) if file.lower().endswith(".xml")]

    if not xml_files:
        print(f"Advertencia: No se encontraron archivos XML en {directorio}")
        return None

    namespaces = {
        'cfdi': 'http://www.sat.gob.mx/cfd/4',
        'cfdi3': 'http://www.sat.gob.mx/cfd/3',
        'nomina12': 'http://www.sat.gob.mx/nomina12'
    }

    archivos_procesados = 0
    archivos_con_error = []

    for filename in xml_files:
        try:
            ruta_archivo = os.path.join(directorio, filename)
            tree = ET.parse(ruta_archivo)
            root = tree.getroot()

            # Percepciones
            for percepcion in root.findall(".//nomina12:Percepcion", namespaces):
                tipo_percepcion = percepcion.get("TipoPercepcion", "")
                clave = percepcion.get("Clave", "")
                concepto = percepcion.get("Concepto", "")
                importe_gravado = percepcion.get("ImporteGravado") or "0"
                importe_exento = percepcion.get("ImporteExento") or "0"
                importe_total = float(importe_gravado) + float(importe_exento)
                ws.append([filename, "Percepción", tipo_percepcion, clave, concepto, importe_gravado, importe_exento, importe_total])
                ws.cell(row=ws.max_row, column=2).fill = green_fill
                if clave and concepto:
                    percepciones_catalogo.add((clave, concepto))

            # Deducciones
            for deduccion in root.findall(".//nomina12:Deduccion", namespaces):
                tipo_deduccion = deduccion.get("TipoDeduccion", "")
                clave = deduccion.get("Clave", "")
                concepto = deduccion.get("Concepto", "")
                importe = deduccion.get("Importe") or "0"
                ws.append([filename, "Deducción", tipo_deduccion, clave, concepto, "", "", importe])
                ws.cell(row=ws.max_row, column=2).fill = red_fill
                if clave and concepto:
                    deducciones_catalogo.add((clave, concepto))

            # Subsidios
            for otro_pago in root.findall(".//nomina12:OtroPago", namespaces):
                tipo_otro_pago = otro_pago.get("TipoOtroPago", "")
                if tipo_otro_pago == "002":
                    clave = otro_pago.get("Clave", "")
                    concepto = otro_pago.get("Concepto", "")
                    importe = otro_pago.get("Importe") or "0"
                    ws.append([filename, "Subsidio", tipo_otro_pago, clave, concepto, "", "", importe])
                    ws.cell(row=ws.max_row, column=2).fill = blue_fill
                    if clave and concepto:
                        subsidios_catalogo.add((clave, concepto))

            archivos_procesados += 1

        except Exception as e:
            archivos_con_error.append((filename, str(e)))
            print(f"Error procesando {filename}: {str(e)}")

    # Crear catálogo
    catalog_ws = wb.create_sheet("Catalogo")
    catalog_ws.append(["Código", "Clave", "Concepto"])
    for clave, concepto in sorted(percepciones_catalogo):
        catalog_ws.append([f"P-{clave[:20]}", clave[:20], concepto[:50]])
    catalog_ws.append([])
    for clave, concepto in sorted(deducciones_catalogo):
        catalog_ws.append([f"D-{clave[:20]}", clave[:20], concepto[:50]])
    catalog_ws.append([])
    for clave, concepto in sorted(subsidios_catalogo):
        catalog_ws.append([f"S-{clave[:20]}", clave[:20], concepto[:50]])

    # Hoja Nómina
    nomina_ws = wb.create_sheet("Nomina")
    nomina_headers = [
        "Consecutivo", "Núm Empleado", "Nombre", "RFC", "CURP", "Puesto", "Departamento", "Tipo de Nomina",
        "Fecha Comprobante", "Num Días Pagados", "Fecha Inicial Pago", "Fecha Final Pago", "Fecha Pago",
        "Total Percepciones", "Total Deducciones", "Total Subsidios", "Total Neto"
    ]
    conceptos_headers = []
    for clave, concepto in sorted(percepciones_catalogo):
        conceptos_headers.append(f"P-{clave[:15]}-{concepto[:20]}")
    for clave, concepto in sorted(deducciones_catalogo):
        conceptos_headers.append(f"D-{clave[:15]}-{concepto[:20]}")
    for clave, concepto in sorted(subsidios_catalogo):
        conceptos_headers.append(f"S-{clave[:15]}-{concepto[:20]}")

    nomina_ws.append(nomina_headers + conceptos_headers)
    for cell in nomina_ws[1]:
        cell.fill = header_fill
        cell.font = Font(bold=True)

    consecutivo = 1
    for filename in xml_files:
        try:
            ruta_archivo = os.path.join(directorio, filename)
            tree = ET.parse(ruta_archivo)
            root = tree.getroot()

            receptor_cfdi = root.find(".//cfdi:Receptor", namespaces)
            if receptor_cfdi is None:
                receptor_cfdi = root.find(".//cfdi3:Receptor", namespaces)
            
            receptor_nomina = root.find(".//nomina12:Receptor", namespaces)
            nomina = root.find(".//nomina12:Nomina", namespaces)

            # Validar que los elementos existan antes de acceder a sus atributos
            if receptor_cfdi is None or receptor_nomina is None or nomina is None:
                print(f"Advertencia: {filename} no tiene la estructura esperada de nómina")
                continue

            num_empleado = receptor_nomina.get("NumEmpleado", "")
            nombre = receptor_cfdi.get("Nombre", "")
            rfc = receptor_cfdi.get("Rfc", "")
            curp = receptor_nomina.get("Curp", "")
            puesto = receptor_nomina.get("Puesto", "")
            departamento = receptor_nomina.get("Departamento", "")
            tipo_nomina = nomina.get("TipoNomina", "")
            fecha_comprobante = root.get("Fecha", "")
            num_dias_pagados = nomina.get("NumDiasPagados", "")
            fecha_inicial_pago = nomina.get("FechaInicialPago", "")
            fecha_final_pago = nomina.get("FechaFinalPago", "")
            fecha_pago = nomina.get("FechaPago", "")
            total_percepciones = float(nomina.get("TotalPercepciones", 0))
            total_deducciones = float(nomina.get("TotalDeducciones", 0))
            total_subsidios = sum(float(otro_pago.get("Importe", 0)) for otro_pago in root.findall(".//nomina12:OtroPago[@TipoOtroPago='002']", namespaces))
            total_neto = total_percepciones - total_deducciones + total_subsidios

            conceptos_valores = {header: "" for header in conceptos_headers}
            for percepcion in root.findall(".//nomina12:Percepcion", namespaces):
                clave = percepcion.get("Clave", "")
                concepto_texto = percepcion.get("Concepto", "")
                importe_total = float(percepcion.get("ImporteGravado", 0)) + float(percepcion.get("ImporteExento", 0))
                header_key = f"P-{clave[:15]}-{concepto_texto[:20]}"
                if header_key in conceptos_valores:
                    conceptos_valores[header_key] = importe_total
                    
            for deduccion in root.findall(".//nomina12:Deduccion", namespaces):
                clave = deduccion.get("Clave", "")
                concepto_texto = deduccion.get("Concepto", "")
                importe = float(deduccion.get("Importe", 0))
                header_key = f"D-{clave[:15]}-{concepto_texto[:20]}"
                if header_key in conceptos_valores:
                    conceptos_valores[header_key] = importe
                    
            for otro_pago in root.findall(".//nomina12:OtroPago[@TipoOtroPago='002']", namespaces):
                clave = otro_pago.get("Clave", "")
                concepto_texto = otro_pago.get("Concepto", "")
                importe = float(otro_pago.get("Importe", 0))
                header_key = f"S-{clave[:15]}-{concepto_texto[:20]}"
                if header_key in conceptos_valores:
                    conceptos_valores[header_key] = importe

            nomina_ws.append([
                consecutivo, num_empleado, nombre, rfc, curp, puesto, departamento, tipo_nomina, fecha_comprobante,
                num_dias_pagados, fecha_inicial_pago, fecha_final_pago, fecha_pago, total_percepciones,
                total_deducciones, total_subsidios, total_neto
            ] + [conceptos_valores[header] for header in conceptos_headers])
            consecutivo += 1

        except Exception as e:
            print(f"Error al procesar nómina de {filename}: {str(e)}")
            continue

    output_path = os.path.join(directorio, 'Percepciones_Deducciones_Subsidios.xlsx')
    wb.save(output_path)
    
    # Resumen de procesamiento
    print(f"\n{'='*60}")
    print(f"Resumen de procesamiento:")
    print(f"Archivos procesados exitosamente: {archivos_procesados}/{len(xml_files)}")
    if archivos_con_error:
        print(f"\nArchivos con errores ({len(archivos_con_error)}):")
        for archivo, error in archivos_con_error:
            print(f"  - {archivo}: {error}")
    print(f"{'='*60}\n")
    
    return output_path


if __name__ == "__main__":
    # Obtener el directorio: argumento o directorio del script
    if len(sys.argv) > 1:
        directorio = sys.argv[1]
    else:
        directorio = os.path.dirname(os.path.abspath(__file__))
    
    print(f"Procesando archivos XML en: {directorio}")
    excel_file = procesar_nomina_xml(directorio)
    if excel_file:
        print(f"Archivo generado exitosamente: {excel_file}")
    else:
        print("No se pudo generar el archivo Excel")