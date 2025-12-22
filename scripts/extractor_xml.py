import os
import sys
import pandas as pd
from typing import Dict, List, Optional

from xml_utils import (
    IssueTracker,
    find_all,
    find_first,
    get_attr,
    load_xml_root,
    normalize_text,
    print_progress,
    to_float,
)


NAMESPACES: Dict[str, str] = {
    "cfdi": "http://www.sat.gob.mx/cfd/4",
    "tfd": "http://www.sat.gob.mx/TimbreFiscalDigital",
    "pago20": "http://www.sat.gob.mx/Pagos20",
}


def extraer_datos_cfdi(xml_file: str, tracker: IssueTracker) -> List[Dict[str, Optional[str]]]:
    print_progress(f"Procesando: {os.path.basename(xml_file)}")
    root = load_xml_root(xml_file, tracker)
    if root is None:
        return []

    filas_datos: List[Dict[str, Optional[str]]] = []
    version_cfdi = get_attr(root, "Version") or "N/A"
    tipo_comprobante = get_attr(root, "TipoDeComprobante") or "N/A"

    tfd = find_first(root, ".//tfd:TimbreFiscalDigital", NAMESPACES)
    uuid = get_attr(tfd, "UUID") or "N/A"
    if uuid == "N/A":
        tracker.warn(f"UUID no encontrado en {os.path.basename(xml_file)}")

    emisor = find_first(root, ".//cfdi:Emisor", NAMESPACES)
    receptor = find_first(root, ".//cfdi:Receptor", NAMESPACES)

    rfc_emisor = get_attr(emisor, "Rfc") or "N/A"
    nombre_emisor = get_attr(emisor, "Nombre") or "Desconocido"
    regimen_fiscal_emisor = get_attr(emisor, "RegimenFiscal") or "N/A"

    rfc_receptor = get_attr(receptor, "Rfc") or "N/A"
    nombre_receptor = get_attr(receptor, "Nombre") or "Desconocido"
    uso_cfdi = get_attr(receptor, "UsoCFDI") or "N/A"

    fecha = get_attr(root, "Fecha") or "N/A"
    metodo_pago = get_attr(root, "MetodoPago") or "N/A"
    forma_pago = get_attr(root, "FormaPago") or "N/A"
    total_general = get_attr(root, "Total") or "0"
    cp_proveedor = get_attr(root, "LugarExpedicion") or "N/A"

    try:
        if tipo_comprobante == "P":
            complemento_pagos = find_first(root, ".//pago20:Pagos", NAMESPACES)
            if complemento_pagos is None:
                tracker.error(f"Complemento de pagos faltante en {os.path.basename(xml_file)}")
                return filas_datos

            for pago in find_all(complemento_pagos, ".//pago20:Pago", NAMESPACES):
                forma_pago_pago = get_attr(pago, "FormaDePagoP") or "N/A"
                monto_pago = to_float(get_attr(pago, "Monto"), 0.0, tracker, "Monto pago")

                doctos = find_all(pago, ".//pago20:DoctoRelacionado", NAMESPACES)
                if not doctos:
                    tracker.warn(f"No hay DoctoRelacionado en pago de {os.path.basename(xml_file)}")

                for docto_relacionado in doctos:
                    filas_datos.append(
                        {
                            "Tipo de Comprobante": tipo_comprobante,
                            "Folio CFDI (UUID)": uuid,
                            "Folio CFDI (UUID) Relacionados": get_attr(docto_relacionado, "IdDocumento") or "N/A",
                            "Tipo Relación": get_attr(docto_relacionado, "TipoRelacion") or "N/A",
                            "Fecha": fecha,
                            "RFC Proveedor": rfc_emisor,
                            "Nombre Proveedor": nombre_emisor,
                            "Régimen Fiscal Proveedor": regimen_fiscal_emisor,
                            "CP del Proveedor": cp_proveedor,
                            "RFC del Cliente": rfc_receptor,
                            "Nombre del Cliente": nombre_receptor,
                            "Uso del CFDI": uso_cfdi,
                            "Método de Pago": metodo_pago,
                            "Forma de Pago": forma_pago_pago,
                            "Descripción": "Pago",
                            "Cantidad": 1,
                            "Unidad": "N/A",
                            "Valor Unitario": monto_pago,
                            "Importe": monto_pago,
                            "Clave Impuesto Trasladado": "N/A",
                            "Impuesto Trasladado": 0.0,
                            "Clave Impuesto Retenido": "N/A",
                            "Impuesto Retenido": 0.0,
                            "Total por Concepto": monto_pago,
                            "Total General": total_general,
                            "Versión CFDI": version_cfdi,
                        }
                    )

        else:
            cfdi_relacionados = find_first(root, ".//cfdi:CfdiRelacionados", NAMESPACES)
            tipo_relacion = get_attr(cfdi_relacionados, "TipoRelacion") or "N/A"

            conceptos = find_all(root, ".//cfdi:Concepto", NAMESPACES)
            if not conceptos:
                tracker.warn(f"No se encontraron conceptos en {os.path.basename(xml_file)}")

            for concepto in conceptos:
                descripcion = get_attr(concepto, "Descripcion") or "N/A"
                cantidad = to_float(get_attr(concepto, "Cantidad"), 0.0, tracker, "Cantidad")
                unidad = get_attr(concepto, "Unidad") or "N/A"
                valor_unitario = to_float(get_attr(concepto, "ValorUnitario"), 0.0, tracker, "ValorUnitario")
                importe = to_float(get_attr(concepto, "Importe"), 0.0, tracker, "Importe")

                traslado = find_first(concepto, ".//cfdi:Traslado", NAMESPACES)
                impuesto_trasladado = to_float(get_attr(traslado, "Importe"), 0.0, tracker, "Traslado")
                clave_impuesto_trasladado = get_attr(traslado, "Impuesto") or "N/A"

                retencion = find_first(concepto, ".//cfdi:Retencion", NAMESPACES)
                impuesto_retenido = to_float(get_attr(retencion, "Importe"), 0.0, tracker, "Retención")
                clave_impuesto_retenido = get_attr(retencion, "Impuesto") or "N/A"

                total_por_concepto = importe + impuesto_trasladado - impuesto_retenido

                filas_datos.append(
                    {
                        "Tipo de Comprobante": tipo_comprobante,
                        "Folio CFDI (UUID)": uuid,
                        "Folio CFDI (UUID) Relacionados": "N/A",
                        "Tipo Relación": tipo_relacion,
                        "Fecha": fecha,
                        "RFC Proveedor": rfc_emisor,
                        "Nombre Proveedor": nombre_emisor,
                        "Régimen Fiscal Proveedor": regimen_fiscal_emisor,
                        "CP del Proveedor": cp_proveedor,
                        "RFC del Cliente": rfc_receptor,
                        "Nombre del Cliente": nombre_receptor,
                        "Uso del CFDI": uso_cfdi,
                        "Método de Pago": metodo_pago,
                        "Forma de Pago": forma_pago,
                        "Descripción": descripcion,
                        "Cantidad": cantidad,
                        "Unidad": unidad,
                        "Valor Unitario": valor_unitario,
                        "Importe": importe,
                        "Clave Impuesto Trasladado": clave_impuesto_trasladado,
                        "Impuesto Trasladado": impuesto_trasladado,
                        "Clave Impuesto Retenido": clave_impuesto_retenido,
                        "Impuesto Retenido": impuesto_retenido,
                        "Total por Concepto": total_por_concepto,
                        "Total General": total_general,
                        "Versión CFDI": version_cfdi,
                    }
                )

    except Exception as exc:
        tracker.error(f"Error procesando {os.path.basename(xml_file)}: {exc}")
        return filas_datos

    return filas_datos


def procesar_archivos_xml_subidos(directorio: str, tracker: IssueTracker) -> Optional[str]:
    todos_los_datos: List[Dict[str, Optional[str]]] = []
    archivos = [f for f in os.listdir(directorio) if f.lower().endswith(".xml")]

    if not archivos:
        tracker.fatal("No se encontraron archivos XML para procesar.")
        return None

    for filename in archivos:
        ruta_archivo = os.path.join(directorio, filename)
        filas = extraer_datos_cfdi(ruta_archivo, tracker)
        if filas:
            todos_los_datos.extend(filas)

    if not todos_los_datos:
        tracker.error("No se generaron datos procesables de los XML.")
        return None

    try:
        df = pd.DataFrame(todos_los_datos)
        archivo_salida = os.path.join(directorio, "cfdi_datos_extraidos.xlsx")
        df.to_excel(archivo_salida, index=False)
        return archivo_salida
    except Exception as exc:
        tracker.fatal(f"No se pudo generar el archivo Excel: {exc}")
        return None


if __name__ == "__main__":
    tracker = IssueTracker()

    if len(sys.argv) <= 1:
        print("ERROR: No se proporcionó directorio", file=sys.stderr)
        sys.exit(2)

    directorio = sys.argv[1]
    excel_path = procesar_archivos_xml_subidos(directorio, tracker)

    tracker.report("CFDI")

    if excel_path and tracker.exit_code == 0:
        print(excel_path)

    sys.exit(tracker.exit_code)
