<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * The whole site, crawled like a visitor. Starting from the public pages (with a scanned table QR and a cart,
 * so the customer screens open), every link on every page is followed and every form is checked against the
 * routes. Nothing may lead to an error, and every page must meet basic accessibility rules. The owner's crawl
 * reaches every module; each other role is crawled from its own home screen and must find no link that
 * leads somewhere it may not go.
 */
class SiteIntegrityTest extends TestCase
{
    private const MAX_PAGES = 400;

    /** @var array<string, array<string, array{status: int, html: ?string, from: string}>> the crawl of each account, kept for the whole run */
    private static array $crawls = [];

    /**
     * Follows every link from the starting pages, signed in as the given demo account.
     *
     * @param  string[]|null  $start  where to begin; the owner starts from the public pages
     * @return array<string, array{status: int, html: ?string, from: string}>
     */
    private function crawl(string $as = 'admin', ?array $start = null): array
    {
        if (isset(self::$crawls[$as])) {
            return self::$crawls[$as];
        }

        $this->signIn($as)->withSession([
            'qr' => ['type' => 'dine-in', 'table' => 3],
            'cart' => [['id' => 1, 'menu_item' => 'latte', 'choices' => ['temp' => ['iced'], 'size' => ['l']], 'notes' => null, 'qty' => 1, 'name' => 'Café Latte', 'unit_price' => 165]],
            'my_orders' => ['demo-0012', 'demo-0008'],
        ]);

        $pages = [];
        $queue = array_map(fn ($path) => [$path, '(start)'], $start ?? ['/', '/navigator', '/login']);
        while ($queue && count($pages) < self::MAX_PAGES) {
            [$path, $from] = array_shift($queue);
            if (isset($pages[$path])) {
                continue;
            }

            $response = $this->get($path);
            $status = $response->getStatusCode();
            $html = str_contains((string) $response->headers->get('content-type'), 'text/html') && $status === 200 ? $response->getContent() : null;
            $pages[$path] = ['status' => $status, 'html' => $html, 'from' => $from];

            if ($status >= 300 && $status < 400) {
                $queue[] = [$this->internalPath($response->headers->get('location')) ?? '/', $path];
            }
            foreach ($html ? $this->links($html) : [] as $link) {
                // The demo navigator lists every screen of every role, so it is left out of a single role's crawl
                if (($target = $this->internalPath($link)) !== null && ! isset($pages[$target]) && ! ($as !== 'admin' && str_starts_with($target, '/navigator'))) {
                    $queue[] = [$target, $path];
                }
            }
        }

        return self::$crawls[$as] = $pages;
    }

    public function test_the_crawl_reaches_every_screen(): void
    {
        $paths = array_keys($this->crawl());

        // Public, customer, cashier, kitchen, inventory and admin screens, including the ones that open from a button
        foreach (['/', '/navigator', '/login', '/order/menu', '/order/cart', '/order/checkout', '/order/mine', '/cashier/pos', '/cashier/orders',
            '/kitchen', '/inventory/ingredients', '/admin', '/admin/menu-items', '/admin/users', '/admin/audit-logs', '/admin/tables/qr', '/admin/demo-reset'] as $screen) {
            $this->assertContains($screen, $paths, "{$screen} is not reachable by following links");
        }
        $this->assertTrue((bool) preg_grep('#^/cashier/orders/\d+/payment$#', $paths), 'take-payment screens are linked from Orders');
        $this->assertTrue((bool) preg_grep('#^/cashier/orders/\d+/receipt$#', $paths), 'receipts are linked from Orders');
        $this->assertTrue((bool) preg_grep('#^/inventory/ingredients/[a-z0-9]+/history$#', $paths), 'stock history is linked from Stock');
        $this->assertTrue((bool) preg_grep('#^/order/demo-\d+(/pay|/done)?$#', $paths), 'the order tracker, wallet and receipt are linked');
        $this->assertGreaterThan(40, count($paths));
    }

    public function test_no_link_leads_to_an_error(): void
    {
        $broken = [];
        foreach ($this->crawl() as $path => $page) {
            if ($page['status'] >= 400) {
                $broken[] = "{$path} → {$page['status']} (linked from {$page['from']})";
            }
        }

        $this->assertSame([], $broken, "Broken links:\n".implode("\n", $broken));
    }

    public function test_each_role_finds_only_links_it_may_follow(): void
    {
        foreach (['cashier' => '/cashier/pos', 'kitchen' => '/kitchen', 'stock' => '/inventory/ingredients'] as $username => $home) {
            $pages = $this->crawl($username, [$home]);
            $broken = [];
            foreach ($pages as $path => $page) {
                if ($page['status'] >= 400) {
                    $broken[] = "{$path} → {$page['status']} (linked from {$page['from']})";
                }
            }

            $this->assertSame([], $broken, "{$username} was offered links it can't open:\n".implode("\n", $broken));
            $this->assertGreaterThanOrEqual(1, count($pages), "{$username} crawl found pages");
            $this->assertNotContains('/admin', array_keys($pages), "{$username} is not offered the dashboard");
        }
    }

    public function test_every_form_posts_to_a_real_route(): void
    {
        $bad = [];
        $forms = 0;
        foreach ($this->crawl() as $path => $page) {
            foreach ($page['html'] ? $this->forms($page['html']) : [] as ['action' => $action, 'method' => $method, 'csrf' => $csrf, 'shared' => $shared]) {
                $forms++;
                $target = $this->internalPath($action);
                if (! $csrf) {
                    $bad[] = "{$path}: {$method} form to {$action} has no @csrf";
                }
                if ($shared) {
                    continue;
                }
                if ($target === null) {
                    $bad[] = "{$path}: form posts outside the app ({$action})";
                } elseif (! $this->routeExists($target, $method)) {
                    $bad[] = "{$path}: no {$method} route for {$target}";
                }
            }
        }

        $this->assertGreaterThan(80, $forms);
        $this->assertSame([], $bad, "Bad forms:\n".implode("\n", array_unique($bad)));
    }

    public function test_every_row_button_that_opens_a_shared_modal_has_a_real_action(): void
    {
        $bad = [];
        $openers = 0;
        foreach ($this->crawl() as $path => $page) {
            foreach ($page['html'] ? $this->openers($page['html']) : [] as ['action' => $action, 'method' => $method, 'opens' => $modal]) {
                $openers++;
                if (! $this->routeExists($this->internalPath($action) ?? '/__outside', $method)) {
                    $bad[] = "{$path}: {$modal} opener has no {$method} route for {$action}";
                }
            }
        }

        $this->assertGreaterThan(100, $openers); // row buttons on Orders, Stock, Menu and Users
        $this->assertSame([], $bad, "Bad openers:\n".implode("\n", array_unique($bad)));
    }

    public function test_every_page_meets_the_accessibility_basics(): void
    {
        $problems = [];
        foreach ($this->crawl() as $path => $page) {
            if (! $page['html']) {
                continue;
            }
            foreach ($this->accessibilityProblems($page['html']) as $problem) {
                $problems[] = "{$path}: {$problem}";
            }
        }

        $this->assertSame([], $problems, "Accessibility problems:\n".implode("\n", array_slice(array_unique($problems), 0, 40)));
    }

    public function test_the_checks_themselves_catch_problems(): void
    {
        $bad = '<html><body><input name="q"><button></button><a href="/x"></a><img src="a.png"><div id="a"></div><div id="a"></div>'
            .'<form method="POST" action="/nowhere"><input name="x"></form></body></html>';

        $problems = implode(' | ', $this->accessibilityProblems($bad));
        foreach (['no lang', 'no <title>', 'id "a" is used 2 times', 'name="q"> has no label', '<button', 'img> without alt', '<a /x>'] as $expected) {
            $this->assertStringContainsString($expected, $problems);
        }

        $this->assertFalse($this->forms($bad)[0]['csrf']);
        $this->assertFalse($this->routeExists('/nowhere', 'POST'));
        $this->assertNull($this->internalPath('https://evil.example/x'));
        $this->assertNull($this->internalPath('mailto:a@b.c'));

        // A GET form's buttons become links, with the hidden fields kept
        $tabs = '<form action="/cashier/orders"><input type="hidden" name="q" value="00"><button name="tab" value="done">Done</button><button name="tab" value="all">All</button></form>';
        $this->assertSame(['/cashier/orders?q=00&tab=done', '/cashier/orders?q=00&tab=all'], $this->links($tabs));

        $this->assertGreaterThan(60, count($this->crawl()), 'the crawl covers the whole site');
    }

    // ---- Helpers --------------------------------------------------------------------------------

    /**
     * Every address a visitor can open from the page: links, plus what each button of a GET form
     * (the tabs and filters) would open.
     *
     * @return string[]
     */
    private function links(string $html): array
    {
        $xpath = $this->xpath($html);
        $links = [];
        foreach ($xpath->query('//a[@href]') as $a) {
            $links[] = $a->getAttribute('href');
        }

        foreach ($xpath->query('//form[not(@method) or translate(@method, "get", "GET")="GET"]') as $form) {
            $base = [];
            foreach ($xpath->query('.//input[@type="hidden" and @name]', $form) as $input) {
                $base[$input->getAttribute('name')] = $input->getAttribute('value');
            }
            $action = $form->getAttribute('action') ?: '/';
            $buttons = $xpath->query('.//button[@name]', $form);

            if (! $buttons->length) {
                $links[] = $action.($base ? '?'.http_build_query($base) : '');
            }
            foreach ($buttons as $button) {
                $links[] = $action.'?'.http_build_query($base + [$button->getAttribute('name') => $button->getAttribute('value')]);
            }
        }

        return $links;
    }

    /**
     * The forms that write (POST, or PUT / PATCH / DELETE through _method).
     *
     * @return array<int, array{action: string, method: string, csrf: bool, shared: bool}>
     */
    private function forms(string $html): array
    {
        $xpath = $this->xpath($html);
        $forms = [];
        foreach ($xpath->query('//form') as $form) {
            $method = strtoupper($form->getAttribute('method') ?: 'GET');
            if ($method === 'GET') {
                continue;
            }
            $spoof = $xpath->query('.//input[@name="_method"]', $form)->item(0);
            $forms[] = [
                'action' => $form->getAttribute('action'),
                'method' => $spoof ? strtoupper($spoof->getAttribute('value')) : $method,
                'csrf' => $xpath->query('.//input[@name="_token"]', $form)->length > 0,
                // A shared modal's action is filled in by the button that opens it (checked in the opener test)
                'shared' => $form->hasAttribute('data-shared-action'),
            ];
        }

        return $forms;
    }

    /**
     * Buttons that open a shared modal for one row: their data-action must be a real route for the modal's method.
     *
     * @return array<int, array{action: string, method: string, opens: string}>
     */
    private function openers(string $html): array
    {
        $xpath = $this->xpath($html);
        $openers = [];
        foreach ($xpath->query('//*[@data-action][@data-modal-open]') as $button) {
            $modal = $button->getAttribute('data-modal-open');
            $spoof = $xpath->query('//*[@id="'.$modal.'"]//input[@name="_method"]')->item(0);
            $openers[] = [
                'action' => $button->getAttribute('data-action'),
                'method' => strtoupper($button->getAttribute('data-method') ?: ($spoof ? $spoof->getAttribute('value') : 'POST')),
                'opens' => $modal,
            ];
        }

        return $openers;
    }

    /** Path + query when the address stays inside this app; null for other sites, anchors, mailto: and the like. */
    private function internalPath(?string $url): ?string
    {
        if ($url === null || $url === '' || str_starts_with($url, '#') || preg_match('#^(mailto|tel|javascript):#i', $url)) {
            return null;
        }

        $parts = parse_url(html_entity_decode($url));
        $host = parse_url(config('app.url'), PHP_URL_HOST);
        if (isset($parts['host']) && ! in_array($parts['host'], [$host, 'localhost', '127.0.0.1'], true)) {
            return null;
        }

        $path = ($parts['path'] ?? '/') ?: '/';

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function routeExists(string $path, string $method): bool
    {
        try {
            app('router')->getRoutes()->match(Request::create($path, $method));

            return true;
        } catch (HttpException) {
            return false;
        }
    }

    /** @return string[] */
    private function accessibilityProblems(string $html): array
    {
        $xpath = $this->xpath($html);
        $problems = [];

        if (! $xpath->query('/html[@lang]')->length) {
            $problems[] = '<html> has no lang';
        }
        if (! trim($xpath->query('//title')->item(0)?->textContent ?? '')) {
            $problems[] = 'no <title>';
        }

        // The same id twice breaks label[for] and aria-controls (a <template> keeps its own content apart)
        $ids = [];
        foreach ($xpath->query('//*[@id][not(ancestor::template)]') as $el) {
            $ids[$el->getAttribute('id')] = ($ids[$el->getAttribute('id')] ?? 0) + 1;
        }
        foreach (array_filter($ids, fn ($n) => $n > 1) as $id => $n) {
            $problems[] = "id \"{$id}\" is used {$n} times";
        }

        // Every control has a name: a label, an aria-label, or its own text
        foreach ($xpath->query('//input[not(@type="hidden") and not(@type="submit") and not(@type="button")] | //select | //textarea') as $el) {
            $named = $el->getAttribute('aria-label') || $el->getAttribute('title')
                || ($el->getAttribute('id') && $xpath->query('//label[@for="'.$el->getAttribute('id').'"]')->length)
                || $xpath->query('ancestor::label', $el)->length;
            if (! $named) {
                $problems[] = '<'.$el->nodeName.' name="'.$el->getAttribute('name').'"> has no label';
            }
        }
        foreach ($xpath->query('//button | //a[@href]') as $el) {
            if (! trim($el->textContent) && ! $el->getAttribute('aria-label') && ! $el->getAttribute('title')) {
                $problems[] = '<'.$el->nodeName.' '.($el->getAttribute('class') ?: $el->getAttribute('href')).'> has no text or aria-label';
            }
        }
        foreach ($xpath->query('//img[not(@alt)]') as $img) {
            $problems[] = '<img> without alt';
        }
        foreach ($xpath->query('//svg[@role="img" and not(@aria-label)]') as $svg) {
            $problems[] = '<svg role="img"> without aria-label';
        }

        return $problems;
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }
}
