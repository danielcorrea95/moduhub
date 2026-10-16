# Schemas da NFS-e Nacional

Cópia dos XSD oficiais publicados em gov.br/nfse (Documentação técnica › Produção restrita), usados por
`XmlSchemaValidator` para validar a DPS e o pedido de evento antes de enviar à Sefin Nacional.

## Versão 1.01 (09/02/2026)

Arquivos copiados sem alteração, com **uma** exceção:

- `tiposSimples_v1.01.xsd`, tipo `TSSerieDPS`: o pattern oficial é `^0{0,4}\d{1,5}$`. Em XML Schema o
  pattern já é ancorado e `^`/`$` são caracteres literais, então o libxml recusa qualquer série. A cópia
  local usa `0{0,4}\d{1,5}`, que é o que o validador da Sefin aplica na prática.

Ao trocar de versão do leiaute, copie a pasta nova inteira, refaça a correção acima se o pattern continuar
igual e ajuste `invoices.layout_version` em `config/invoices.php`.
