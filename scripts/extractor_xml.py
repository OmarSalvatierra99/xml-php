import xml.etree.ElementTree as ET
import pandas as pd
import os
import sys

def extraer_datos_cfdi(xml_file):
    print(f"Processing file: {xml_file}")
    try:
        tree = ET.parse(xml_file)
        root = tree.getroot()
    except Exception as e:
        print(f"Error reading XML file {xml_file}: {e}", file=sys.stderr)
        return [], None

    # Namespaces for CFDI 4.0 and pagos
    namespaces = {
        'cfdi': 'http://www.sat.gob.mx/cfd/4',
        'tfd': 'http://www.sat.gob.mx/TimbreFiscalDigital',
        'pago20': 'http://www.sat.gob.mx/Pagos20',
    }

    filas_datos = []
    version_cfdi = root.attrib.get('Version', 'N/A')

    try:
        # General data
        tipo_comprobante = root.attrib.get('TipoDeComprobante', 'N/A')

        tfd = root.find('.//tfd:TimbreFiscalDigital', namespaces)
        uuid = tfd.attrib['UUID'] if tfd is not None else 'N/A'

        emisor = root.find('.//cfdi:Emisor', namespaces)
        rfc_emisor = emisor.attrib.get('Rfc', 'N/A') if emisor is not None else 'N/A'
        nombre_emisor = emisor.attrib.get('Nombre', 'Desconocido') if emisor is not None else 'Desconocido'
        regimen_fiscal_emisor = emisor.attrib.get('RegimenFiscal', 'N/A') if emisor is not None else 'N/A'

        receptor = root.find('.//cfdi:Receptor', namespaces)
        rfc_receptor = receptor.attrib.get('Rfc', 'N/A') if receptor is not None else 'N/A'
        nombre_receptor = receptor.attrib.get('Nombre', 'Desconocido') if receptor is not None else 'Desconocido'
        uso_cfdi = receptor.attrib.get('UsoCFDI', 'N/A') if receptor is not None else 'N/A'

        fecha = root.attrib.get('Fecha', 'N/A')
        metodo_pago = root.attrib.get('MetodoPago', 'N/A')
        forma_pago = root.attrib.get('FormaPago', 'N/A')
        total_general = root.attrib.get('Total', '0')
        cp_proveedor = root.attrib.get('LugarExpedicion', 'N/A')

        # Handle pagos (Tipo de Comprobante = "P")
        if tipo_comprobante == "P":
            complemento_pagos = root.find('.//pago20:Pagos', namespaces)
            if complemento_pagos is not None:
                for pago in complemento_pagos.findall('.//pago20:Pago', namespaces):
                    forma_pago_pago = pago.attrib.get('FormaDePagoP', 'N/A')
                    monto_pago = float(pago.attrib.get('Monto', '0'))
                    moneda_pago = pago.attrib.get('MonedaP', 'N/A')

                    for docto_relacionado in pago.findall('.//pago20:DoctoRelacionado', namespaces):
                        filas_datos.append({
                            "Tipo de Comprobante": tipo_comprobante,
                            "Folio CFDI (UUID)": uuid,
                            "Folio CFDI (UUID) Relacionados": docto_relacionado.attrib.get('IdDocumento', 'N/A'),
                            "Tipo Relación": docto_relacionado.attrib.get('TipoRelacion', 'N/A'),
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
                            "Versión CFDI": version_cfdi
                        })

        else:  # Handle tipos de comprobante "I" (Ingreso) y "E" (Egreso)
            # Buscar relaciones en el nodo CfdiRelacionados
            cfdi_relacionados = root.find('.//cfdi:CfdiRelacionados', namespaces)
            tipo_relacion = cfdi_relacionados.attrib.get('TipoRelacion', 'N/A') if cfdi_relacionados is not None else 'N/A'

            conceptos = root.findall('.//cfdi:Concepto', namespaces)
            for concepto in conceptos:
                descripcion = concepto.attrib.get('Descripcion', 'N/A')
                cantidad = float(concepto.attrib.get('Cantidad', '0'))
                unidad = concepto.attrib.get('Unidad', 'N/A')
                valor_unitario = float(concepto.attrib.get('ValorUnitario', '0'))
                importe = float(concepto.attrib.get('Importe', '0'))

                traslado = concepto.find('.//cfdi:Traslado', namespaces)
                impuesto_trasladado = float(traslado.attrib.get('Importe', '0')) if traslado is not None else 0.0
                clave_impuesto_trasladado = traslado.attrib.get('Impuesto', 'N/A') if traslado is not None else 'N/A'

                retencion = concepto.find('.//cfdi:Retencion', namespaces)
                impuesto_retenido = float(retencion.attrib.get('Importe', '0')) if retencion is not None else 0.0
                clave_impuesto_retenido = retencion.attrib.get('Impuesto', 'N/A') if retencion is not None else 'N/A'

                total_por_concepto = importe + impuesto_trasladado - impuesto_retenido

                filas_datos.append({
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
                    "Versión CFDI": version_cfdi
                })

    except Exception as e:
        print(f"Error processing file {xml_file}: {e}", file=sys.stderr)
        return [], None

    return filas_datos, version_cfdi


def procesar_archivos_xml_subidos(directorio):
    todos_los_datos = []

    archivos = [f for f in os.listdir(directorio) if f.endswith('.xml')]

    for filename in archivos:
        ruta_archivo = os.path.join(directorio, filename)
        filas, version_cfdi = extraer_datos_cfdi(ruta_archivo)
        if filas:
            todos_los_datos.extend(filas)

    if todos_los_datos:
        df = pd.DataFrame(todos_los_datos)
        archivo_salida = os.path.join(directorio, 'cfdi_datos_extraidos.xlsx')
        df.to_excel(archivo_salida, index=False)
        return archivo_salida

    return None


if __name__ == "__main__":
    if len(sys.argv) > 1:
        directorio = sys.argv[1]
        archivo_excel = procesar_archivos_xml_subidos(directorio)
        if archivo_excel:
            print(archivo_excel)  # ✅ PHP recibirá esta ruta
        else:
            print("ERROR: No se procesaron archivos correctamente", file=sys.stderr)
            sys.exit(1)
    else:
        print("ERROR: No se proporcionó directorio", file=sys.stderr)
        sys.exit(1)