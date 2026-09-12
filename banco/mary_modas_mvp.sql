CREATE DATABASE IF NOT EXISTS mary_modas_mvp
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE mary_modas_mvp;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS auditoria;
DROP TABLE IF EXISTS historico_status_pedido;
DROP TABLE IF EXISTS pagamentos;
DROP TABLE IF EXISTS pedido_itens;
DROP TABLE IF EXISTS pedidos;
DROP TABLE IF EXISTS carrinho_itens;
DROP TABLE IF EXISTS carrinhos;
DROP TABLE IF EXISTS promocoes;
DROP TABLE IF EXISTS produtos;
DROP TABLE IF EXISTS fornecedores;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS usuarios;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE usuarios (
  id_usuario BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  telefone VARCHAR(20) NULL,
  senha_hash VARCHAR(255) NOT NULL,
  perfil ENUM('Cliente','Administrador') NOT NULL DEFAULT 'Cliente',
  status ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  cep VARCHAR(9) NOT NULL,
  logradouro VARCHAR(150) NOT NULL,
  numero VARCHAR(20) NOT NULL,
  data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_usuarios_perfil_status (perfil, status)
) ENGINE=InnoDB;

CREATE TABLE categorias (
  id_categoria BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL UNIQUE,
  descricao VARCHAR(255) NULL,
  status ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE fornecedores (
  id_fornecedor BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome_razao_social VARCHAR(160) NOT NULL,
  cpf_cnpj VARCHAR(18) NULL UNIQUE,
  telefone VARCHAR(20) NULL,
  email VARCHAR(160) NULL,
  status ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE produtos (
  id_produto BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_categoria BIGINT UNSIGNED NOT NULL,
  id_fornecedor BIGINT UNSIGNED NULL,
  nome VARCHAR(150) NOT NULL,
  descricao TEXT NULL,
  preco DECIMAL(10,2) NOT NULL,
  tamanho VARCHAR(30) NOT NULL,
  cor VARCHAR(60) NOT NULL,
  estoque INT UNSIGNED NOT NULL DEFAULT 0,
  imagem VARCHAR(255) NULL,
  status ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_produtos_categoria FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria),
  CONSTRAINT fk_produtos_fornecedor FOREIGN KEY (id_fornecedor) REFERENCES fornecedores(id_fornecedor),
  UNIQUE KEY uq_produto_variacao (nome, id_categoria, tamanho, cor),
  INDEX idx_produtos_status_estoque (status, estoque)
) ENGINE=InnoDB;

CREATE TABLE promocoes (
  id_promocao BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  modo ENUM('Automática','Cupom') NOT NULL,
  codigo VARCHAR(50) NULL UNIQUE,
  tipo_desconto ENUM('Percentual','Valor') NOT NULL,
  valor_desconto DECIMAL(10,2) NOT NULL,
  data_inicio DATE NOT NULL,
  data_fim DATE NOT NULL,
  aplicacao ENUM('Geral','Produto','Categoria') NOT NULL,
  id_produto_alvo BIGINT UNSIGNED NULL,
  id_categoria_alvo BIGINT UNSIGNED NULL,
  status ENUM('Ativa','Inativa') NOT NULL DEFAULT 'Ativa',
  data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_promocao_produto FOREIGN KEY (id_produto_alvo) REFERENCES produtos(id_produto),
  CONSTRAINT fk_promocao_categoria FOREIGN KEY (id_categoria_alvo) REFERENCES categorias(id_categoria),
  CONSTRAINT chk_promocao_valor CHECK (valor_desconto > 0),
  CONSTRAINT chk_promocao_percentual CHECK (tipo_desconto <> 'Percentual' OR (valor_desconto >= 1 AND valor_desconto <= 50)),
  CONSTRAINT chk_promocao_periodo CHECK (data_fim >= data_inicio),
  CONSTRAINT chk_promocao_modo CHECK ((modo = 'Automática' AND codigo IS NULL) OR (modo = 'Cupom' AND codigo IS NOT NULL)),
  CONSTRAINT chk_promocao_alvo CHECK (
    (aplicacao = 'Geral' AND id_produto_alvo IS NULL AND id_categoria_alvo IS NULL) OR
    (aplicacao = 'Produto' AND id_produto_alvo IS NOT NULL AND id_categoria_alvo IS NULL) OR
    (aplicacao = 'Categoria' AND id_produto_alvo IS NULL AND id_categoria_alvo IS NOT NULL)
  )
) ENGINE=InnoDB;

CREATE TABLE carrinhos (
  id_carrinho BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario BIGINT UNSIGNED NOT NULL,
  status ENUM('Aberto','Em Pagamento','Finalizado') NOT NULL DEFAULT 'Aberto',
  cupom VARCHAR(50) NULL,
  data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_carrinhos_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
  INDEX idx_carrinhos_usuario_status (id_usuario, status)
) ENGINE=InnoDB;

CREATE TABLE carrinho_itens (
  id_carrinho_item BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_carrinho BIGINT UNSIGNED NOT NULL,
  id_produto BIGINT UNSIGNED NOT NULL,
  quantidade INT UNSIGNED NOT NULL,
  CONSTRAINT fk_carrinho_itens_carrinho FOREIGN KEY (id_carrinho) REFERENCES carrinhos(id_carrinho) ON DELETE CASCADE,
  CONSTRAINT fk_carrinho_itens_produto FOREIGN KEY (id_produto) REFERENCES produtos(id_produto),
  UNIQUE KEY uq_carrinho_produto (id_carrinho, id_produto)
) ENGINE=InnoDB;

CREATE TABLE pedidos (
  id_pedido BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero_pedido VARCHAR(30) NULL UNIQUE,
  id_usuario BIGINT UNSIGNED NOT NULL,
  id_carrinho BIGINT UNSIGNED NOT NULL UNIQUE,
  id_promocao BIGINT UNSIGNED NULL,
  status ENUM('Aguardando Pagamento','Pago','Em Separação','Enviado','Entregue','Cancelado') NOT NULL DEFAULT 'Aguardando Pagamento',
  cep_entrega VARCHAR(9) NOT NULL,
  logradouro_entrega VARCHAR(150) NOT NULL,
  numero_entrega VARCHAR(20) NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  desconto DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  motivo_cancelamento VARCHAR(255) NULL,
  data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pedidos_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
  CONSTRAINT fk_pedidos_carrinho FOREIGN KEY (id_carrinho) REFERENCES carrinhos(id_carrinho),
  CONSTRAINT fk_pedidos_promocao FOREIGN KEY (id_promocao) REFERENCES promocoes(id_promocao),
  INDEX idx_pedidos_usuario_status (id_usuario, status),
  INDEX idx_pedidos_data (data_criacao)
) ENGINE=InnoDB;

CREATE TABLE pedido_itens (
  id_pedido_item BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_pedido BIGINT UNSIGNED NOT NULL,
  id_produto BIGINT UNSIGNED NOT NULL,
  nome_produto VARCHAR(150) NOT NULL,
  tamanho VARCHAR(30) NOT NULL,
  cor VARCHAR(60) NOT NULL,
  preco_unitario DECIMAL(10,2) NOT NULL,
  quantidade INT UNSIGNED NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_pedido_itens_pedido FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido) ON DELETE CASCADE,
  CONSTRAINT fk_pedido_itens_produto FOREIGN KEY (id_produto) REFERENCES produtos(id_produto)
) ENGINE=InnoDB;

CREATE TABLE pagamentos (
  id_pagamento BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_pedido BIGINT UNSIGNED NOT NULL,
  forma ENUM('PIX','Cartão de Crédito','Boleto Bancário') NOT NULL,
  status ENUM('Pendente','Aprovado','Recusado','Estornado') NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  referencia_transacao VARCHAR(120) NULL UNIQUE,
  data_tentativa DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pagamentos_pedido FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido) ON DELETE CASCADE,
  INDEX idx_pagamentos_pedido_status (id_pedido, status)
) ENGINE=InnoDB;

CREATE TABLE historico_status_pedido (
  id_historico BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_pedido BIGINT UNSIGNED NOT NULL,
  id_usuario_responsavel BIGINT UNSIGNED NULL,
  status_anterior VARCHAR(30) NULL,
  status_novo VARCHAR(30) NOT NULL,
  data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_historico_pedido FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido) ON DELETE CASCADE,
  CONSTRAINT fk_historico_usuario FOREIGN KEY (id_usuario_responsavel) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB;

CREATE TABLE auditoria (
  id_auditoria BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario BIGINT UNSIGNED NULL,
  acao VARCHAR(80) NOT NULL,
  entidade VARCHAR(80) NOT NULL,
  id_registro BIGINT UNSIGNED NOT NULL,
  detalhes TEXT NULL,
  data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_auditoria_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB;

INSERT INTO usuarios (id_usuario,nome,email,telefone,senha_hash,perfil,status,cep,logradouro,numero) VALUES
(1,'Administrador Mary','admin@marymodas.local','(44) 99999-0000','$2y$12$f.tmmmgHFU2gByMjhB55J.iSTQCinpjOXx8isqEuA1U.RF.WzA8ye','Administrador','Ativo','87500-000','Avenida Brasil','120'),
(2,'Ana Cliente','cliente@marymodas.local','(44) 98888-1111','$2y$12$1anlNyDSZu5wUo3WBWO6t.ID3PuJA5AHVZR9/dQKKBhaTJXYQmNRy','Cliente','Ativo','87500-100','Rua das Flores','45');

INSERT INTO categorias (id_categoria,nome,descricao,status) VALUES
(1,'Vestidos','Vestidos casuais e sociais','Ativo'),
(2,'Blusas','Blusas e camisetas','Ativo'),
(3,'Acessórios','Bolsas e acessórios','Ativo');

INSERT INTO fornecedores (id_fornecedor,nome_razao_social,cpf_cnpj,telefone,email,status) VALUES
(1,'Fornecedor Exemplo Ltda.','12.345.678/0001-90','(44) 3333-0000','contato@fornecedor.com','Ativo'),
(2,'Moda Sul Distribuidora','98.765.432/0001-10','(44) 3222-5555','vendas@modasul.com','Ativo');

INSERT INTO produtos (id_produto,id_categoria,id_fornecedor,nome,descricao,preco,tamanho,cor,estoque,imagem,status) VALUES
(1,1,1,'Vestido Floral','Vestido floral confeccionado em tecido leve.',129.90,'M','Preto',20,NULL,'Ativo'),
(2,1,2,'Vestido Midi','Vestido midi elegante para eventos.',159.90,'G','Vinho',8,NULL,'Ativo'),
(3,2,1,'Blusa Básica','Blusa confortável para o dia a dia.',69.90,'P','Branco',35,NULL,'Ativo'),
(4,3,2,'Bolsa Urbana','Bolsa compacta para uso diário.',99.90,'Único','Caramelo',12,NULL,'Ativo');

INSERT INTO promocoes (id_promocao,nome,modo,codigo,tipo_desconto,valor_desconto,data_inicio,data_fim,aplicacao,id_produto_alvo,id_categoria_alvo,status) VALUES
(1,'Semana Mary','Automática',NULL,'Percentual',10,DATE_SUB(CURDATE(), INTERVAL 7 DAY),DATE_ADD(CURDATE(), INTERVAL 30 DAY),'Categoria',NULL,1,'Ativa'),
(2,'Cupom MARY20','Cupom','MARY20','Percentual',20,DATE_SUB(CURDATE(), INTERVAL 1 DAY),DATE_ADD(CURDATE(), INTERVAL 30 DAY),'Geral',NULL,NULL,'Ativa');
