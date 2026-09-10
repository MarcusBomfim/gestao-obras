-- Dados de demonstração. Usa ON CONFLICT DO NOTHING para poder rodar quantas
-- vezes for preciso sem duplicar nem sobrescrever o que já foi apontado.

INSERT INTO obras (
    codigo, nome, cliente,
    logradouro, numero, bairro, cidade, uf, cep,
    data_de_inicio, prazo_em_dias,
    responsavel_tecnico, registro_profissional, situacao
) VALUES
    ('OBR-2026-001', 'Reforma estrutural do galpão 3', 'Terminal Portuário Litoral S.A.',
     'Avenida Eng. Augusto Barata', '780', 'Macuco', 'Santos', 'SP', '11015300',
     '2026-02-02', 120, 'Marcus Bomfim', 'CREA-SP 5069874521/D', 'em_andamento'),

    ('OBR-2026-002', 'Edifício residencial Vista Serra', 'Construtora Vale Verde Ltda.',
     'Rua Frei Gaspar', '1420', 'Centro', 'São Vicente', 'SP', '11310061',
     '2026-03-16', 540, 'Helena Duarte', 'CAU A118472-3', 'planejada'),

    ('OBR-2025-014', 'Pavimentação do pátio de contêineres', 'Terminal Portuário Litoral S.A.',
     'Avenida Perimetral', '2100', 'Alemoa', 'Santos', 'SP', '11095400',
     '2025-08-04', 90, 'Marcus Bomfim', 'CREA-SP 5069874521/D', 'concluida')
ON CONFLICT (codigo) DO NOTHING;


INSERT INTO servicos (
    obra_codigo, codigo, descricao, unidade,
    quantidade_prevista, preco_unitario, quantidade_executada
) VALUES
    -- Galpão 3: obra em andamento, avanço parcial.
    ('OBR-2026-001', 'DEM-01', 'Demolição de alvenaria existente com remoção de entulho',
     'm3', 145.00, 96.40, 145.00),
    ('OBR-2026-001', 'EST-01', 'Reforço estrutural em perfil metálico ASTM A572',
     'kg', 8400.00, 22.75, 5250.00),
    ('OBR-2026-001', 'ALV-01', 'Alvenaria de vedação em bloco cerâmico 14x19x39',
     'm2', 320.00, 78.50, 96.00),
    ('OBR-2026-001', 'COB-01', 'Cobertura em telha metálica trapezoidal 0,50mm',
     'm2', 1180.00, 142.30, 0.00),
    ('OBR-2026-001', 'PIN-01', 'Pintura epóxi sobre estrutura metálica, duas demãos',
     'm2', 640.00, 54.90, 0.00),
    ('OBR-2026-001', 'ADM-01', 'Administração local e mobilização de canteiro',
     'vb', 1.00, 84000.00, 0.00),

    -- Vista Serra: obra ainda planejada, nada executado.
    ('OBR-2026-002', 'FUN-01', 'Estaca hélice contínua diâmetro 40cm',
     'm', 1860.00, 189.00, 0.00),
    ('OBR-2026-002', 'CON-01', 'Concreto usinado fck 30 MPa bombeado',
     'm3', 2240.00, 612.50, 0.00),
    ('OBR-2026-002', 'FOR-01', 'Forma em chapa compensada plastificada 18mm',
     'm2', 9800.00, 68.20, 0.00),
    ('OBR-2026-002', 'ACO-01', 'Armadura em aço CA-50, corte, dobra e montagem',
     'kg', 186000.00, 12.90, 0.00),
    ('OBR-2026-002', 'ESQ-01', 'Porta de madeira semi-oca 80x210 com batente',
     'un', 148.00, 640.00, 0.00),

    -- Pátio de contêineres: obra concluída, tudo executado.
    ('OBR-2025-014', 'TER-01', 'Terraplenagem e regularização do subleito',
     'm2', 14500.00, 18.60, 14500.00),
    ('OBR-2025-014', 'PAV-01', 'Pavimento rígido em concreto fck 35 MPa, espessura 22cm',
     'm2', 14500.00, 214.80, 14500.00),
    ('OBR-2025-014', 'DRE-01', 'Drenagem em tubo de concreto DN 400',
     'm', 620.00, 168.40, 620.00),
    ('OBR-2025-014', 'SIN-01', 'Sinalização horizontal em resina acrílica',
     'm2', 840.00, 42.70, 840.00)
ON CONFLICT (obra_codigo, codigo) DO NOTHING;
