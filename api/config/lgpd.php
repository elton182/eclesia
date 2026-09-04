<?php

declare(strict_types=1);

/**
 * Configurações de conformidade LGPD (Lei 13.709/2018).
 *
 * Dados pessoais em repouso usam elgibor-solution/laravel-database-encryption.
 * Em models Eloquent, declare protected $encryptable e use o trait EncryptedAttribute.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Campos tipicamente pessoais (referência)
    |--------------------------------------------------------------------------
    |
    | Checklist para novos models/migrações. Nem todos existem ainda;
    | use ao criar features com dados de pessoas físicas.
    |
    */
    'personal_data_fields' => [
        'name',
        'email',
        'cpf',
        'rg',
        'phone',
        'mobile',
        'address',
        'address_number',
        'address_complement',
        'neighborhood',
        'city',
        'state',
        'zip_code',
        'birth_date',
        'document_number',
    ],

    /*
    |--------------------------------------------------------------------------
    | Regras de schema
    |--------------------------------------------------------------------------
    */
    'schema' => [
        // Ciphertext é maior que o plain text; use string(500+) ou text
        'encrypted_column_type' => 'string',
        'encrypted_column_length' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Busca e validação
    |--------------------------------------------------------------------------
    */
    'query' => [
        // Use whereEncrypted / orWhereEncrypted em vez de where()
        // Validação: unique_encrypted / exists_encrypted
    ],

    /*
    |--------------------------------------------------------------------------
    | Retenção e exclusão (orientações)
    |--------------------------------------------------------------------------
    */
    'retention' => [
        // Preferir soft-delete + anonimização ao apagar dados pessoais
        'prefer_anonymization' => true,
    ],
];
