<?php
/**
 * lel since 20.08.18
 */
namespace App\Domain\Import\ArticleDeactivation;

use DB;

class AbandonedArticleIDProvider implements ArticleIDProvider
{
    public function getArticleIDs(): iterable
    {
        $importFileQuery = <<<EOT
select id
from import_files
where import_files.type = 'base'
order by import_files.created_at desc
limit 7
EOT;

        $importFileIds = collect(DB::select($importFileQuery))->pluck('id');
        $importFileIdPlaceholders = $importFileIds->map(function () { return '?'; })->implode(', ');

        $query = <<<EOT
select a.id,
       (select count(1)
        from article_imports
        where article_imports.article_id = a.id
          and article_imports.import_file_id in ($importFileIdPlaceholders)) occurrences_in_last_7_imports
from articles as a
where occurrences_in_last_7_imports = 0
and a.sw_article_id is not null
and a.is_active = 1
EOT;

        $result = DB::select($query, $importFileIds->toArray());

        return collect($result)->pluck('id');
    }
}
