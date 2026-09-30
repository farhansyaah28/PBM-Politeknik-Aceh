<?php
declare(strict_types=1);

namespace App\Support;

final class Paginator
{
    public function __construct(
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage = 15,
    ) {}

    public static function fromRequest(int $total, mixed $requestedPage, int $perPage = 15): self
    {
        $perPage = max(1, min(100, $perPage));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = filter_var($requestedPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        return new self($total, min($page, $lastPage), $perPage);
    }

    public function offset(): int { return ($this->page - 1) * $this->perPage; }
    public function lastPage(): int { return max(1, (int) ceil($this->total / $this->perPage)); }
    public function hasPages(): bool { return $this->lastPage() > 1; }

    /** @param array<string, scalar|null> $query */
    public function links(string $path, array $query = []): string
    {
        if (!$this->hasPages()) return '';
        $esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $url = function (int $page) use ($path, $query): string {
            $params = array_filter($query, static fn ($value): bool => $value !== null && $value !== '');
            $params['page'] = $page;
            return $path . '?' . http_build_query($params);
        };
        $items = [];
        if ($this->page > 1) $items[] = '<a href="' . $esc($url($this->page - 1)) . '">Sebelumnya</a>';
        foreach (range(max(1, $this->page - 2), min($this->lastPage(), $this->page + 2)) as $page) {
            $items[] = $page === $this->page
                ? '<span aria-current="page">' . $page . '</span>'
                : '<a href="' . $esc($url($page)) . '">' . $page . '</a>';
        }
        if ($this->page < $this->lastPage()) $items[] = '<a href="' . $esc($url($this->page + 1)) . '">Berikutnya</a>';
        return '<nav class="pagination" aria-label="Paginasi"><span>' . $this->total . ' data</span><div>' . implode('', $items) . '</div></nav>';
    }
}
