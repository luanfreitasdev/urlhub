<?php

namespace App\Livewire\Table;

use App\Models\Url;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Footer;
use PowerComponents\LivewirePowerGrid\Header;
use PowerComponents\LivewirePowerGrid\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

/**
 * @codeCoverageIgnore
 */
class BaseUrlTable extends PowerGridComponent
{
    const STR_LIMIT = 90;

    public int $perPage = 25;

    public string $sortDirection = 'desc';

    public string $sortField = 'urls.id';

    public string $primaryKey = 'urls.id';

    public function getUserIdBuilder(Builder $query): Builder
    {
        return $query;
    }

    public function setUp(): array
    {
        return [
            Header::make()
                ->showToggleColumns()
                ->showSearchInput(),
            Footer::make()
                ->showPerPage($this->perPage)
                ->showRecordCount('full'),
        ];
    }

    public function datasource(): Builder
    {
        return Url::query()
            ->join('users', 'urls.user_id', '=', 'users.id')
            ->leftJoinSub(DB::table('visits')
                ->select('url_id', DB::raw('COUNT(id) as visits_count'))
                ->groupBy('url_id'), 'visits_counts', function (JoinClause $join) {
                    $join->on('urls.id', '=', 'visits_counts.url_id');
                })
            ->leftJoinSub(DB::table('visits')
                    ->select('url_id', DB::raw('SUM(CASE WHEN is_first_click = 1 THEN 1 ELSE 0 END) as unique_click_count'))
                    ->groupBy('url_id'), 'unique_click_counts', function (JoinClause $join) {
                        $join->on('urls.id', '=', 'unique_click_counts.url_id');
                    })
            ->where(fn (Builder $query) => $this->getUserIdBuilder($query))
            ->select(
                'urls.id as id',
                'users.name as author',
                'urls.title',
                'urls.keyword',
                'urls.destination',
                'urls.created_at',
                DB::raw('COALESCE(visits_counts.visits_count, 0) as visits_count'),
                DB::raw('COALESCE(unique_click_counts.unique_click_count, 0) as unique_click_count')
            );
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('author', function (Url $url) {
                return view('components.table.author', ['name' => $url->author])
                    ->render();
            })
            ->add('keyword', function (Url $url) {
                return view('components.table.keyword', ['shortUrl' => $url->short_url, 'keyword' => $url->keyword])
                    ->render();
            })
            ->add('destination', function (Url $url) {
                return view('components.table.destination', [
                    'title' => $url->title,
                    'destination' => $url->destination,
                    'limit' => self::STR_LIMIT,
                ])->render();
            })
            ->add('t_clicks', function (Url $url) {
                return view('components.table.visit', ['clicks' => $url->visits_count, 'uniqueClicks' => $url->unique_click_count])
                    ->render();
            })
            ->add('created_at_formatted', function (Url $url) {
                return view('components.table.date-created', ['createdAt' => $url->created_at])
                    ->render();
            })
            ->add('action', function (Url $url) {
                return view('components.table.action-button', ['url' => $url])
                    ->render();
            });
    }

    /**
     * @return array<Column>
     */
    public function columns(): array
    {
        return [
            Column::make('Owner', 'author')
                ->sortable()
                ->searchable(),

            Column::make('Short URL', 'keyword')
                ->sortable()
                ->searchable(),

            Column::make('Destination URL', 'destination')
                ->sortable()
                ->searchable(),
            Column::make('title', 'title')
                ->searchable()
                ->hidden(),

            Column::make('CLICKS', 't_clicks'),

            Column::make('CREATED AT', 'created_at_formatted', 'created_at')
                ->searchable()
                ->sortable(),

            Column::make('ACTIONS', 'action'),
        ];
    }
}
