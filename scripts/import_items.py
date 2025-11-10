#!/usr/bin/env python3
"""
InventoX - Items Import Script
Script Python para importação de artigos a partir de ficheiros CSV/XLSX
"""

import sys
import os
import json
import pandas as pd
from sqlalchemy import create_engine, text
from sqlalchemy.exc import SQLAlchemyError
from dotenv import load_dotenv

# Carregar variáveis de ambiente
load_dotenv()

# Configuração da base de dados
DB_HOST = os.getenv('DB_HOST', 'mysql')
DB_NAME = os.getenv('DB_NAME', 'inventox')
DB_USER = os.getenv('DB_USER', 'inventox_user')
DB_PASS = os.getenv('DB_PASS', 'change_me')
DB_PORT = os.getenv('DB_PORT', '3306')

# Criar engine SQLAlchemy
DATABASE_URL = f"mysql+pymysql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}"

def import_items(file_path):
    """
    Importa artigos de um ficheiro CSV ou XLSX
    """
    try:
        # Ler ficheiro
        file_extension = os.path.splitext(file_path)[1].lower()
        
        if file_extension == '.csv':
            df = pd.read_csv(file_path, encoding='utf-8')
        elif file_extension in ['.xls', '.xlsx']:
            df = pd.read_excel(file_path)
        else:
            return {
                'success': False,
                'message': f'Formato de ficheiro não suportado: {file_extension}',
                'imported': 0,
                'updated': 0,
                'errors': []
            }
        
        # Debug: mostrar colunas originais
        if os.getenv('DEBUG', 'False').lower() == 'true':
            print(f"DEBUG: Colunas originais: {df.columns.tolist()}", file=sys.stderr)
        
        # Normalizar nomes das colunas (lowercase, sem espaços, remover BOM)
        df.columns = df.columns.str.replace('\ufeff', '', regex=False).str.lower().str.strip().str.replace(' ', '_')
        
        # Remover colunas vazias ou com nomes inválidos
        df = df.loc[:, df.columns != '']  # Remove colunas com nome vazio
        df = df.loc[:, ~df.columns.str.contains('^unnamed:', case=False, na=False)]  # Remove colunas "Unnamed"
        
        # Debug: mostrar colunas após normalização e limpeza (apenas se DEBUG=True)
        if os.getenv('DEBUG', 'False').lower() == 'true':
            print(f"DEBUG: Colunas normalizadas e limpas: {df.columns.tolist()}", file=sys.stderr)
        
        # Mapear colunas esperadas (incluindo variações com acentos e pontuação)
        column_mapping = {
            'barcode': 'barcode',
            'codigo_barras': 'barcode',
            'código_barras': 'barcode',
            'cód._barras': 'barcode',
            'cod._barras': 'barcode',
            'codigo': 'barcode',
            'name': 'name',
            'nome': 'name',
            'artigo': 'name',
            'produto': 'name',
            'descricao': 'description',
            'descrição': 'description',
            'description': 'description',
            'categoria': 'category',
            'category': 'category',
            'quantity': 'quantity',
            'quantidade': 'quantity',
            'qtd': 'quantity',
            'qtd._stock': 'quantity',
            'qtd_stock': 'quantity',
            'stock': 'quantity',
            'min_quantity': 'min_quantity',
            'quantidade_minima': 'min_quantity',
            'qtd_minima': 'min_quantity',
            'unit_price': 'unit_price',
            'preco_unitario': 'unit_price',
            'preço_unitario': 'unit_price',
            'custo_unitário': 'unit_price',
            'preco': 'unit_price',
            'preço': 'unit_price',
            'pvp': 'unit_price',
            'pvp1': 'unit_price',
            'location': 'location',
            'localizacao': 'location',
            'localização': 'location',
            'supplier': 'supplier',
            'fornecedor': 'supplier'
        }
        
        # Renomear colunas conforme mapeamento
        rename_dict = {}
        for old_col in df.columns:
            if old_col in column_mapping:
                rename_dict[old_col] = column_mapping[old_col]
        
        df.rename(columns=rename_dict, inplace=True)
        
        # Debug: mostrar colunas finais (apenas se DEBUG=True)
        if os.getenv('DEBUG', 'False').lower() == 'true':
            print(f"DEBUG: Colunas finais: {df.columns.tolist()}", file=sys.stderr)
            print(f"DEBUG: Mapeamento aplicado: {rename_dict}", file=sys.stderr)
        
        # Validar colunas obrigatórias APÓS normalização/renomeação
        required_columns = ['barcode', 'name']
        missing_columns = [col for col in required_columns if col not in df.columns]
        if missing_columns:
            return {
                'success': False,
                'message': f'Colunas obrigatórias em falta: {", ".join(missing_columns)}',
                'imported': 0,
                'updated': 0,
                'errors': []
            }
        
        # Preparar dados
        engine = create_engine(DATABASE_URL, pool_pre_ping=True)
        
        imported = 0
        updated = 0
        errors = []
        
        with engine.connect() as conn:
            for index, row in df.iterrows():
                try:
                    barcode = str(row.get('barcode', '')).strip()
                    name = str(row.get('name', '')).strip()
                    
                    if not barcode or not name:
                        errors.append(f"Linha {index + 2}: Código de barras e nome são obrigatórios")
                        continue
                    
                    # Preparar dados para inserção
                    description = str(row.get('description', '')).strip() or None
                    quantity = int(row.get('quantity', 0) or 0)
                    min_quantity = int(row.get('min_quantity', 0) or 0)
                    unit_price = float(row.get('unit_price', 0) or 0)
                    location = str(row.get('location', '')).strip() or None
                    supplier = str(row.get('supplier', '')).strip() or None
                    category_name = str(row.get('category', '')).strip()
                    
                    # Verificar se categoria existe, se não, criar
                    category_id = None
                    if category_name:
                        category_result = conn.execute(
                            text("SELECT id FROM categories WHERE name = :name"),
                            {'name': category_name}
                        )
                        category_row = category_result.fetchone()
                        
                        if category_row:
                            category_id = category_row[0]
                        else:
                            # Criar nova categoria
                            conn.execute(
                                text("INSERT INTO categories (name) VALUES (:name)"),
                                {'name': category_name}
                            )
                            conn.commit()
                            category_result = conn.execute(
                                text("SELECT id FROM categories WHERE name = :name"),
                                {'name': category_name}
                            )
                            category_id = category_result.fetchone()[0]
                    
                    # Verificar se artigo já existe
                    existing = conn.execute(
                        text("SELECT id FROM items WHERE barcode = :barcode"),
                        {'barcode': barcode}
                    ).fetchone()
                    
                    if existing:
                        # Atualizar artigo existente
                        conn.execute(
                            text("""
                                UPDATE items 
                                SET name = :name,
                                    description = :description,
                                    category_id = :category_id,
                                    quantity = :quantity,
                                    min_quantity = :min_quantity,
                                    unit_price = :unit_price,
                                    location = :location,
                                    supplier = :supplier,
                                    updated_at = CURRENT_TIMESTAMP
                                WHERE barcode = :barcode
                            """),
                            {
                                'barcode': barcode,
                                'name': name,
                                'description': description,
                                'category_id': category_id,
                                'quantity': quantity,
                                'min_quantity': min_quantity,
                                'unit_price': unit_price,
                                'location': location,
                                'supplier': supplier
                            }
                        )
                        updated += 1
                    else:
                        # Inserir novo artigo
                        conn.execute(
                            text("""
                                INSERT INTO items 
                                (barcode, name, description, category_id, quantity, 
                                 min_quantity, unit_price, location, supplier)
                                VALUES 
                                (:barcode, :name, :description, :category_id, :quantity,
                                 :min_quantity, :unit_price, :location, :supplier)
                            """),
                            {
                                'barcode': barcode,
                                'name': name,
                                'description': description,
                                'category_id': category_id,
                                'quantity': quantity,
                                'min_quantity': min_quantity,
                                'unit_price': unit_price,
                                'location': location,
                                'supplier': supplier
                            }
                        )
                        imported += 1
                    
                    conn.commit()
                    
                except Exception as e:
                    errors.append(f"Linha {index + 2}: {str(e)}")
                    conn.rollback()
                    continue
        
        engine.dispose()
        
        return {
            'success': True,
            'message': f'Importação concluída: {imported} importados, {updated} atualizados',
            'imported': imported,
            'updated': updated,
            'errors': errors
        }
        
    except Exception as e:
        return {
            'success': False,
            'message': f'Erro ao processar ficheiro: {str(e)}',
            'imported': 0,
            'updated': 0,
            'errors': [str(e)]
        }

def main():
    """
    Função principal
    """
    if len(sys.argv) < 2:
        result = {
            'success': False,
            'message': 'Caminho do ficheiro não fornecido',
            'imported': 0,
            'updated': 0,
            'errors': []
        }
        print(json.dumps(result, ensure_ascii=False))
        sys.exit(1)
    
    file_path = sys.argv[1]
    
    if not os.path.exists(file_path):
        result = {
            'success': False,
            'message': f'Ficheiro não encontrado: {file_path}',
            'imported': 0,
            'updated': 0,
            'errors': []
        }
        print(json.dumps(result, ensure_ascii=False))
        sys.exit(1)
    
    result = import_items(file_path)
    print(json.dumps(result, ensure_ascii=False))
    
    if not result['success']:
        sys.exit(1)

if __name__ == '__main__':
    main()

