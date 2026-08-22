<?php
declare(strict_types=1);

namespace Leo;

/**
 * Request handling and routing.
 *
 * One entry point, a flat route table, and templates rendered by including a
 * PHP file with the view data in scope. No framework: on a shared host with no
 * shell there is no Composer to install one with, and the site does not need it.
 */
final class App
{
    private Store $store;
    private Auth $auth;

    /** Shared with every template. */
    private array $shared = [];

    /** The variables in scope for the view currently rendering. */
    private array $current = [];

    public function __construct(
        private string $dataFile,
        private string $viewsDir,
        private array $config,
    ) {
        $this->store = new Store($this->dataFile);
        $this->auth = new Auth(
            (string) ($this->config['admin_password'] ?? ''),
            (string) ($this->config['session_secret'] ?? '') ?: (string) ($this->config['admin_password'] ?? 'leo')
        );
    }

    public function run(): void
    {
        $path = $this->path();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'POST' && !Auth::sameOrigin()) {
            http_response_code(403);
            echo 'Cross-site request blocked.';
            return;
        }

        $this->prepareContext();

        try {
            if (str_starts_with($path, '/admin')) {
                $this->routeAdmin($path, $method);
                return;
            }
            $this->routePublic($path);
        } catch (\Throwable $error) {
            http_response_code(500);
            $this->render('error', [
                'title' => 'Something went wrong',
                'message' => $error->getMessage(),
            ]);
        }
    }

    /** The request path, with the trailing slash normalised away. */
    private function path(): string
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = rtrim($path, '/');
        return $path === '' ? '/' : $path;
    }

    /**
     * Everything time-sensitive resolves against one date per request.
     *
     * Signed in, ?asOf=YYYY-MM-DD renders the site as it will look on that date
     * with drafts included. Both are ignored for anonymous visitors, so a shared
     * link can never expose unpublished content.
     */
    private function prepareContext(): void
    {
        $isAdmin = $this->auth->isAdmin();
        $site = $this->store->site();

        $asOf = null;
        if ($isAdmin && Schedule::isDate($_GET['asOf'] ?? null)) {
            $asOf = (string) $_GET['asOf'];
        }

        $today = $asOf ?? Schedule::todayIn((string) ($site['timezone'] ?? 'America/Phoenix'));
        $preview = $isAdmin && ($asOf !== null || ($_GET['preview'] ?? '') === '1');
        $enrollment = Schedule::resolveWindow($this->store->enrollment(), $today);

        $this->shared = [
            'site' => $site,
            'today' => $today,
            'asOf' => $asOf,
            'preview' => $preview,
            'isAdmin' => $isAdmin,
            'enrollment' => $enrollment,
            'enrollmentLabel' => Schedule::describeWindow($enrollment),
            'enrollmentSettings' => $this->store->enrollment(),
            'navPages' => Content::navPages(Content::publicPages($this->store, $today, $preview)),
            'announcements' => Content::activeAnnouncements($this->store, $today, $enrollment['state']),
            'scholarshipNames' => array_map(
                static fn (array $s) => (string) ($s['name'] ?? ''),
                $this->store->list('scholarships')
            ),
            'currentPath' => $this->path(),
        ];
    }

    // ---------------------------------------------------------------- public

    private function routePublic(string $path): void
    {
        $today = $this->shared['today'];
        $preview = $this->shared['preview'];

        if ($path === '/') {
            $scholarships = Content::publicScholarships($this->store, $today, $preview);
            $recipients = Content::publicRecipients($this->store, $today, $preview);

            $this->render('home', [
                'title' => $this->shared['site']['name'],
                'scholarships' => $scholarships,
                'openScholarships' => Content::openScholarships($scholarships),
                'featuredRecipients' => Content::featuredRecipients($recipients, 3),
                'stats' => Content::awardStats($recipients),
            ]);
            return;
        }

        if ($path === '/scholarships') {
            $scholarships = Content::publicScholarships($this->store, $today, $preview);
            $this->render('scholarships', [
                'title' => 'Available scholarships',
                'scholarships' => $scholarships,
                'openScholarships' => Content::openScholarships($scholarships),
            ]);
            return;
        }

        if (preg_match('#^/scholarships/([a-z0-9-]+)$#', $path, $match)) {
            $record = $this->store->findBySlug('scholarships', $match[1]);
            if ($record === null) {
                $this->notFound();
                return;
            }
            $scholarship = Content::resolveScholarship($record, $this->store->enrollment(), $today);
            if (!$scholarship['published'] && !$preview) {
                $this->notFound();
                return;
            }

            $recipients = array_values(array_filter(
                Content::publicRecipients($this->store, $today, $preview),
                static fn (array $r) => ($r['scholarship'] ?? null) === $scholarship['name']
            ));

            $this->render('scholarship', [
                'title' => $scholarship['name'],
                'scholarship' => $scholarship,
                'recipients' => $recipients,
            ]);
            return;
        }

        if ($path === '/recipients') {
            $all = Content::publicRecipients($this->store, $today, $preview);

            $years = [];
            foreach ($all as $recipient) {
                if (!empty($recipient['year'])) {
                    $years[(string) $recipient['year']] = true;
                }
            }
            // Cast back from PHP's integer keys, or the strict comparison
            // below never matches and the year filter silently does nothing.
            $years = array_map(strval(...), array_keys($years));
            rsort($years, SORT_STRING);

            $requested = (string) ($_GET['year'] ?? '');
            $year = in_array($requested, $years, true) ? $requested : null;
            $recipients = $year === null
                ? $all
                : array_values(array_filter($all, static fn (array $r) => (string) ($r['year'] ?? '') === $year));

            $this->render('recipients', [
                'title' => 'Scholarship recipients',
                'groups' => Content::groupRecipientsByYear($recipients),
                'years' => $years,
                'selectedYear' => $year,
                'stats' => Content::awardStats($all),
            ]);
            return;
        }

        // Editable pages live at the site root, so this is the last thing tried.
        if (preg_match('#^/([a-z0-9-]+)$#', $path, $match)) {
            $page = $this->store->findBySlug('pages', $match[1]);
            if ($page !== null && ($preview || Schedule::isPublished($page, $today))) {
                $this->render('page', ['title' => $page['title'] ?? '', 'page' => $page]);
                return;
            }
        }

        $this->notFound();
    }

    private function notFound(): void
    {
        http_response_code(404);
        $this->render('404', ['title' => 'Page not found']);
    }


    // ----------------------------------------------------------------- admin

    private function routeAdmin(string $path, string $method): void
    {
        $resources = Admin::resources();

        if ($path === '/admin/login') {
            if ($method === 'POST') {
                $this->handleLogin();
                return;
            }
            if ($this->auth->isAdmin()) {
                $this->redirect('/admin');
                return;
            }
            $this->render('admin/login', [
                'title' => 'Staff sign in',
                'error' => isset($_GET['error']) ? 'That password did not match. Try again.' : null,
                'configured' => $this->auth->isConfigured(),
                'next' => (string) ($_GET['next'] ?? '/admin'),
            ]);
            return;
        }

        if ($path === '/admin/logout' && $method === 'POST') {
            $this->auth->logout();
            $this->redirect('/');
            return;
        }

        // Everything below this line requires a signed-in admin.
        if (!$this->auth->isAdmin()) {
            $this->redirect('/admin/login?next=' . urlencode($path));
            return;
        }

        if ($path === '/admin') {
            $this->adminDashboard();
            return;
        }

        if ($path === '/admin/settings') {
            if ($method === 'POST') {
                $this->saveSettings();
                return;
            }
            $this->render('admin/settings', [
                'title' => 'Site settings',
                'saved' => ($_GET['saved'] ?? '') === '1',
            ]);
            return;
        }

        if (preg_match('#^/admin/([a-z]+)(?:/([^/]+))?(?:/(delete|move))?$#', $path, $match)) {
            $name = $match[1];
            $id = $match[2] ?? null;
            $action = $match[3] ?? null;

            if (!isset($resources[$name])) {
                $this->notFound();
                return;
            }
            $config = $resources[$name];

            if ($id === null) {
                $this->adminList($name, $config);
                return;
            }

            if ($method === 'POST') {
                if ($action === 'delete') {
                    $this->store->remove($name, $id);
                    $this->redirect("/admin/$name");
                    return;
                }
                if ($action === 'move') {
                    $direction = ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down';
                    $this->store->reorder($name, $id, $direction);
                    $this->redirect("/admin/$name");
                    return;
                }
                $this->adminSave($name, $config, $id);
                return;
            }

            if ($id === 'new') {
                $this->render('admin/form', [
                    'title' => 'New ' . strtolower($config['label']),
                    'name' => $name,
                    'config' => $config,
                    'record' => ['window' => ['type' => 'inherit'], 'publish' => []],
                    'isNew' => true,
                    'saved' => false,
                ]);
                return;
            }

            $record = $this->store->find($name, $id);
            if ($record === null) {
                $this->notFound();
                return;
            }
            $this->render('admin/form', [
                'title' => (string) ($record[$config['titleKey']] ?? $config['label']),
                'name' => $name,
                'config' => $config,
                'record' => $record,
                'isNew' => false,
                'saved' => ($_GET['saved'] ?? '') === '1',
            ]);
            return;
        }

        $this->notFound();
    }

    private function handleLogin(): void
    {
        $next = (string) ($_POST['next'] ?? '/admin');
        if (!$this->auth->passwordMatches($_POST['password'] ?? null)) {
            $this->redirect('/admin/login?error=1&next=' . urlencode($next));
            return;
        }
        $this->auth->login();
        $this->redirect(str_starts_with($next, '/') ? $next : '/admin');
    }

    private function adminDashboard(): void
    {
        $today = $this->shared['today'];

        $scholarships = array_map(
            fn (array $s) => Content::resolveScholarship($s, $this->store->enrollment(), $today),
            $this->store->list('scholarships')
        );
        $recipients = $this->store->list('recipients');
        $published = array_values(array_filter(
            $recipients,
            static fn (array $r) => Schedule::isPublished($r, $today)
        ));

        $this->render('admin/dashboard', [
            'title' => 'Dashboard',
            'scholarships' => $scholarships,
            'openCount' => count(array_filter($scholarships, static fn (array $s) => $s['isOpen'])),
            'recipients' => $recipients,
            'publishedRecipients' => $published,
            'stats' => Content::awardStats($published),
            'pageCount' => count($this->store->list('pages')),
            'announcementCount' => count($this->store->list('announcements')),
        ]);
    }

    private function adminList(string $name, array $config): void
    {
        $today = $this->shared['today'];

        $items = array_map(function (array $item) use ($name, $today) {
            $item['published'] = Schedule::isPublished($item, $today);
            $item['resolved'] = $name === 'scholarships'
                ? Content::resolveScholarship($item, $this->store->enrollment(), $today)
                : null;
            return $item;
        }, $this->store->list($name));

        $this->render('admin/list', [
            'title' => $config['plural'],
            'name' => $name,
            'config' => $config,
            'items' => $items,
        ]);
    }

    private function adminSave(string $name, array $config, string $id): void
    {
        $existing = $id === 'new' ? [] : ($this->store->find($name, $id) ?? []);
        $record = Admin::applyFields($existing, $_POST, $config['fields']);

        $hasSlug = false;
        foreach ($config['fields'] as $field) {
            if ($field['key'] === 'slug') {
                $hasSlug = true;
                break;
            }
        }
        if ($hasSlug) {
            $record['slug'] = Store::slugify(
                ($record['slug'] ?? '') !== '' ? $record['slug'] : ($record[$config['titleKey']] ?? '')
            );
        }
        if ($id !== 'new') {
            $record['id'] = $id;
        }

        $saved = $this->store->upsert($name, $record);
        $this->redirect("/admin/$name/" . $saved['id'] . '?saved=1');
    }

    private function saveSettings(): void
    {
        $body = $_POST;
        $text = static fn (string $key): string => trim((string) ($body[$key] ?? ''));

        $impact = [];
        foreach ([0, 1, 2] as $index) {
            $value = trim((string) ($body["impact{$index}value"] ?? ''));
            $label = trim((string) ($body["impact{$index}label"] ?? ''));
            if ($value !== '' || $label !== '') {
                $impact[] = ['value' => $value, 'label' => $label];
            }
        }

        $this->store->updateSite([
            'name' => $text('name') !== '' ? $text('name') : ($this->store->site()['name'] ?? 'LEO Foundation'),
            'legalName' => $text('legalName'),
            'tagline' => $text('tagline'),
            'mission' => $text('mission'),
            'timezone' => $text('timezone') !== '' ? $text('timezone') : 'America/Phoenix',
            'email' => $text('email'),
            'phone' => $text('phone'),
            'location' => $text('location'),
            'ein' => $text('ein'),
            'donateUrl' => $text('donateUrl'),
            'facebookUrl' => $text('facebookUrl'),
            'impact' => $impact,
        ]);

        $this->store->updateEnrollment([
            'type' => ($body['enrollmentType'] ?? '') === 'fixed' ? 'fixed' : 'annual',
            'opensOn' => $text('enrollmentOpensOn'),
            'closesOn' => $text('enrollmentClosesOn'),
            'instructions' => $text('instructions'),
            'awardedNote' => $text('awardedNote'),
        ]);

        $this->redirect('/admin/settings?saved=1');
    }

    private function redirect(string $to): void
    {
        header('Location: ' . $to, true, 302);
    }

    // ----------------------------------------------------------------- views


    public function render(string $view, array $data = []): void
    {
        $file = $this->viewsDir . '/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("missing view: $view");
        }

        $previous = $this->current;
        $this->current = array_merge($this->shared, $data);

        $app = $this;
        $store = $this->store;
        extract($this->current, EXTR_OVERWRITE);

        try {
            require $file;
        } finally {
            $this->current = $previous;
        }
    }

    /**
     * Render a partial with the calling view's variables still in scope, the
     * way an EJS include behaves — a partial that needs $title should not have
     * to be handed it at every call site.
     */
    public function partial(string $view, array $data = []): void
    {
        $this->render('partials/' . $view, array_merge($this->current, $data));
    }

    public function store(): Store
    {
        return $this->store;
    }

    public function auth(): Auth
    {
        return $this->auth;
    }

    public function shared(): array
    {
        return $this->shared;
    }
}
