<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('igrejas', function (Blueprint $table) {
            if (! Schema::hasColumn('igrejas', 'slug')) {
                $table->string('slug')->nullable()->unique()->after('nome');
            }
            if (! Schema::hasColumn('igrejas', 'publicado_no_site')) {
                $table->boolean('publicado_no_site')->default(false)->after('email');
            }
            if (! Schema::hasColumn('igrejas', 'descricao_publica')) {
                $table->text('descricao_publica')->nullable()->after('publicado_no_site');
            }
            if (! Schema::hasColumn('igrejas', 'horario_missas')) {
                $table->text('horario_missas')->nullable()->after('descricao_publica');
            }
            if (! Schema::hasColumn('igrejas', 'banner_media_id')) {
                $table->ulid('banner_media_id')->nullable()->after('horario_missas');
            }
        });

        if (! Schema::hasTable('site_settings')) {
            Schema::create('site_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('publicado')->default(false);
                $table->string('titulo')->default('Site');
                $table->string('subtitulo')->nullable();
                $table->string('logo_path')->nullable();
                $table->string('favicon_path')->nullable();
                $table->json('cores')->nullable();
                $table->json('seo')->nullable();
                $table->json('contato')->nullable();
                $table->json('menu')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('site_media')) {
            Schema::create('site_media', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('path');
                $table->string('alt')->nullable();
                $table->string('mime')->nullable();
                // users.id é bigint (não ULID) — ver create_users_table
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('site_pages')) {
            Schema::create('site_pages', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('slug')->unique();
                $table->string('titulo');
                $table->string('status', 32)->default('rascunho');
                $table->boolean('is_home')->default(false);
                $table->unsignedInteger('ordem')->default(0);
                $table->boolean('mostrar_no_menu')->default(true);
                $table->json('seo')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('site_blocks')) {
            Schema::create('site_blocks', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('site_page_id')->constrained('site_pages')->cascadeOnDelete();
                $table->string('tipo', 64);
                $table->unsignedInteger('ordem')->default(0);
                $table->boolean('visivel')->default(true);
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('site_comunicados')) {
            Schema::create('site_comunicados', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('titulo');
                $table->text('resumo')->nullable();
                $table->longText('corpo');
                $table->foreignUlid('capa_media_id')->nullable()->constrained('site_media')->nullOnDelete();
                $table->timestamp('publicado_em')->nullable();
                $table->string('status', 32)->default('rascunho');
                $table->boolean('destaque')->default(false);
                $table->foreignUlid('igreja_id')->nullable()->constrained('igrejas')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pastorais')) {
            Schema::create('pastorais', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('igreja_id')->constrained('igrejas')->cascadeOnDelete();
                $table->string('nome');
                $table->text('descricao_publica')->nullable();
                $table->text('contato_publico')->nullable();
                $table->unsignedInteger('ordem')->default(0);
                $table->boolean('publicado_no_site')->default(false);
                $table->boolean('ativa')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('site_forms')) {
            Schema::create('site_forms', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('nome');
                $table->string('slug')->unique();
                $table->text('descricao')->nullable();
                $table->boolean('ativo')->default(true);
                $table->string('destino_email')->nullable();
                $table->string('sucesso_mensagem')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('site_form_fields')) {
            Schema::create('site_form_fields', function (Blueprint $table) {
                $table->id();
                $table->foreignUlid('site_form_id')->constrained('site_forms')->cascadeOnDelete();
                $table->string('nome');
                $table->string('label');
                $table->string('tipo', 32)->default('text');
                $table->boolean('obrigatorio')->default(false);
                $table->json('opcoes')->nullable();
                $table->unsignedInteger('ordem')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('site_form_submissions')) {
            Schema::create('site_form_submissions', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('site_form_id')->constrained('site_forms')->cascadeOnDelete();
                $table->text('payload');
                $table->string('ip', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_form_submissions');
        Schema::dropIfExists('site_form_fields');
        Schema::dropIfExists('site_forms');
        Schema::dropIfExists('pastorais');
        Schema::dropIfExists('site_comunicados');
        Schema::dropIfExists('site_blocks');
        Schema::dropIfExists('site_pages');
        Schema::dropIfExists('site_media');
        Schema::dropIfExists('site_settings');

        Schema::table('igrejas', function (Blueprint $table) {
            $cols = array_values(array_filter([
                Schema::hasColumn('igrejas', 'slug') ? 'slug' : null,
                Schema::hasColumn('igrejas', 'publicado_no_site') ? 'publicado_no_site' : null,
                Schema::hasColumn('igrejas', 'descricao_publica') ? 'descricao_publica' : null,
                Schema::hasColumn('igrejas', 'horario_missas') ? 'horario_missas' : null,
                Schema::hasColumn('igrejas', 'banner_media_id') ? 'banner_media_id' : null,
            ]));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
