<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$pdo = db();
const POR_PAGINA = 8;

/* ---------- Utilidades de navegación ---------- */
function params_lista(array $src): array
{
    $p = [];
    $q = trim((string)($src['q'] ?? ''));
    $m = trim((string)($src['marca'] ?? ''));
    $pg = (int)($src['page'] ?? 1);
    if ($q !== '')  { $p['q'] = $q; }
    if ($m !== '')  { $p['marca'] = $m; }
    if ($pg > 1)    { $p['page'] = $pg; }
    return $p;
}

function url_lista(array $p): string
{
    return 'index.php' . ($p ? '?' . http_build_query($p) : '');
}

function color_marca(string $marca): int
{
    return crc32(mb_strtolower($marca)) % 360;
}

/* ---------- Acciones (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $volver = url_lista(params_lista($_POST));

    if (!csrf_valido($_POST['csrf'] ?? null)) {
        flash('error', 'La sesión expiró. Vuelve a intentarlo.');
        header('Location: ' . $volver);
        exit;
    }

    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($accion === 'eliminar' && $id > 0) {
        $st = $pdo->prepare('DELETE FROM articulos WHERE id = :id');
        $st->execute([':id' => $id]);
        flash('ok', 'Artículo eliminado.');
        header('Location: ' . $volver);
        exit;
    }

    if ($accion === 'crear' || $accion === 'editar') {
        $nombre    = trim((string)($_POST['nombre'] ?? ''));
        $marca     = trim((string)($_POST['marca'] ?? ''));
        $ubicacion = trim((string)($_POST['ubicacion'] ?? ''));
        $unidades  = filter_var($_POST['unidades'] ?? '', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 1000000],
        ]);

        $errores = [];
        if ($nombre === '' || mb_strlen($nombre) > 150)       { $errores[] = 'El nombre es obligatorio (máx. 150 caracteres).'; }
        if ($marca === '' || mb_strlen($marca) > 100)         { $errores[] = 'La marca es obligatoria (máx. 100 caracteres).'; }
        if ($ubicacion === '' || mb_strlen($ubicacion) > 150) { $errores[] = 'La ubicación es obligatoria (máx. 150 caracteres).'; }
        if ($unidades === false)                              { $errores[] = 'Las unidades deben ser un número entero de 0 en adelante.'; }
        if ($accion === 'editar' && $id <= 0)                 { $errores[] = 'Artículo no válido.'; }

        if ($errores) {
            flash('error', implode(' ', $errores));
            $_SESSION['reabrir'] = [
                'accion' => $accion, 'id' => $id, 'nombre' => $nombre,
                'marca' => $marca, 'unidades' => $_POST['unidades'] ?? '', 'ubicacion' => $ubicacion,
            ];
            header('Location: ' . $volver);
            exit;
        }

        if ($accion === 'crear') {
            $st = $pdo->prepare('INSERT INTO articulos (nombre, marca, unidades, ubicacion) VALUES (:n, :m, :u, :l)');
            $st->execute([':n' => $nombre, ':m' => $marca, ':u' => $unidades, ':l' => $ubicacion]);
            flash('ok', '¡Artículo "' . $nombre . '" creado!');
        } else {
            $st = $pdo->prepare('UPDATE articulos SET nombre = :n, marca = :m, unidades = :u, ubicacion = :l WHERE id = :id');
            $st->execute([':n' => $nombre, ':m' => $marca, ':u' => $unidades, ':l' => $ubicacion, ':id' => $id]);
            flash('ok', 'Cambios guardados en "' . $nombre . '".');
        }
        header('Location: ' . $volver);
        exit;
    }

    header('Location: ' . $volver);
    exit;
}

/* ---------- Consulta (GET) ---------- */
$filtros = params_lista($_GET);
$q      = $filtros['q'] ?? '';
$marcaF = $filtros['marca'] ?? '';
$pagina = max(1, (int)($filtros['page'] ?? 1));

$where = [];
$bind = [];
if ($q !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $where[] = '(nombre LIKE :q1 OR marca LIKE :q2 OR ubicacion LIKE :q3)';
    $bind[':q1'] = $bind[':q2'] = $bind[':q3'] = $like;
}
if ($marcaF !== '') {
    $where[] = 'marca = :marca';
    $bind[':marca'] = $marcaF;
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$st = $pdo->prepare("SELECT COUNT(*) FROM articulos $sqlWhere");
$st->execute($bind);
$totalFiltrado = (int)$st->fetchColumn();
$totalPaginas = max(1, (int)ceil($totalFiltrado / POR_PAGINA));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * POR_PAGINA;

$st = $pdo->prepare("SELECT * FROM articulos $sqlWhere ORDER BY actualizado_en DESC, id DESC LIMIT :lim OFFSET :off");
foreach ($bind as $k => $v) { $st->bindValue($k, $v); }
$st->bindValue(':lim', POR_PAGINA, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$articulos = $st->fetchAll();

$stats = $pdo->query('SELECT COUNT(*) AS total, COALESCE(SUM(unidades),0) AS unidades,
                             COUNT(DISTINCT marca) AS marcas, COUNT(DISTINCT ubicacion) AS ubicaciones
                      FROM articulos')->fetch();
$marcas = $pdo->query('SELECT DISTINCT marca FROM articulos ORDER BY marca')->fetchAll(PDO::FETCH_COLUMN);
$ubicaciones = $pdo->query('SELECT DISTINCT ubicacion FROM articulos ORDER BY ubicacion')->fetchAll(PDO::FETCH_COLUMN);

$flash = tomar_flash();
$reabrir = $_SESSION['reabrir'] ?? null;
unset($_SESSION['reabrir']);
$token = csrf_token();

function enlace_pagina(array $filtros, int $n): string
{
    $f = $filtros;
    unset($f['page']);
    if ($n > 1) { $f['page'] = $n; }
    return h(url_lista($f));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Inventario Aqua · Gestor de artículos</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:opsz,wght@6..12,400;6..12,600;6..12,700;6..12,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<!-- Fondo Frutiger Aero: cielo, rayos de sol, burbujas y colinas -->
<div class="escena" aria-hidden="true">
  <div class="sol"></div>
  <span class="burbuja b1"></span><span class="burbuja b2"></span><span class="burbuja b3"></span>
  <span class="burbuja b4"></span><span class="burbuja b5"></span><span class="burbuja b6"></span>
  <div class="colina c1"></div><div class="colina c2"></div>
</div>

<main class="pagina">

  <header class="cabecera vidrio">
    <div class="marca-app">
      <div class="orbe-logo" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="#fff" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>
      </div>
      <div>
        <h1>Inventario Aqua</h1>
        <p>Tus artículos con su marca, unidades y ubicación, siempre a la vista.</p>
      </div>
    </div>
    <button class="btn btn-verde" type="button" data-abrir-crear>
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
      Nuevo artículo
    </button>
  </header>

  <?php if ($flash): ?>
    <div class="aviso aviso-<?= h($flash['tipo']) ?> vidrio" role="status" data-aviso>
      <?= h($flash['mensaje']) ?>
      <button type="button" class="aviso-cerrar" aria-label="Cerrar aviso" data-cerrar-aviso>×</button>
    </div>
  <?php endif; ?>

  <section class="orbes" aria-label="Resumen del inventario">
    <article class="orbe o-aqua"><span class="orbe-num"><?= number_format((int)$stats['total']) ?></span><span class="orbe-txt">Artículos</span></article>
    <article class="orbe o-lima"><span class="orbe-num"><?= number_format((int)$stats['unidades']) ?></span><span class="orbe-txt">Unidades en total</span></article>
    <article class="orbe o-naranja"><span class="orbe-num"><?= number_format((int)$stats['marcas']) ?></span><span class="orbe-txt">Marcas</span></article>
    <article class="orbe o-violeta"><span class="orbe-num"><?= number_format((int)$stats['ubicaciones']) ?></span><span class="orbe-txt">Ubicaciones</span></article>
  </section>

  <section class="panel vidrio">
    <form class="barra" method="get" action="index.php" role="search">
      <label class="campo-busqueda">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" name="q" value="<?= h($q) ?>" placeholder="Busca por nombre, marca o ubicación" aria-label="Buscar artículos">
      </label>
      <select name="marca" aria-label="Filtrar por marca">
        <option value="">Todas las marcas</option>
        <?php foreach ($marcas as $m): ?>
          <option value="<?= h($m) ?>" <?= $m === $marcaF ? 'selected' : '' ?>><?= h($m) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-aqua" type="submit">Buscar</button>
      <?php if ($q !== '' || $marcaF !== ''): ?>
        <a class="btn btn-gris" href="index.php">Limpiar</a>
      <?php endif; ?>
    </form>

    <?php if (!$articulos): ?>
      <div class="vacio">
        <div class="orbe-logo grande" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg>
        </div>
        <?php if ($q !== '' || $marcaF !== ''): ?>
          <h2>No encontramos artículos con esos filtros</h2>
          <p>Prueba con otra palabra o quita el filtro de marca.</p>
          <a class="btn btn-aqua" href="index.php">Ver todos los artículos</a>
        <?php else: ?>
          <h2>Aún no hay artículos</h2>
          <p>Agrega el primero para empezar a llevar tu inventario.</p>
          <button class="btn btn-verde" type="button" data-abrir-crear>Agregar el primer artículo</button>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="tabla-envoltura">
        <table class="tabla">
          <thead>
            <tr><th>Artículo</th><th>Marca</th><th class="c">Unidades</th><th>Ubicación</th><th class="c">Acciones</th></tr>
          </thead>
          <tbody>
          <?php foreach ($articulos as $a):
              $u = (int)$a['unidades'];
              $nivel = $u === 0 ? 'agotado' : ($u <= 5 ? 'bajo' : 'ok');
              $etq = $u === 0 ? 'Agotado' : ($u <= 5 ? 'Quedan pocas' : 'Disponible');
          ?>
            <tr>
              <td data-label="Artículo"><strong class="nombre"><?= h($a['nombre']) ?></strong></td>
              <td data-label="Marca"><span class="chip" style="--h:<?= color_marca($a['marca']) ?>"><?= h($a['marca']) ?></span></td>
              <td data-label="Unidades" class="c"><span class="unidades u-<?= $nivel ?>" title="<?= $etq ?>"><?= number_format($u) ?></span></td>
              <td data-label="Ubicación">
                <span class="ubicacion">
                  <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                  <?= h($a['ubicacion']) ?>
                </span>
              </td>
              <td data-label="Acciones" class="c">
                <div class="acciones">
                  <button class="icono icono-aqua" type="button" title="Editar" aria-label="Editar <?= h($a['nombre']) ?>"
                          data-editar
                          data-id="<?= (int)$a['id'] ?>"
                          data-nombre="<?= h($a['nombre']) ?>"
                          data-marca="<?= h($a['marca']) ?>"
                          data-unidades="<?= $u ?>"
                          data-ubicacion="<?= h($a['ubicacion']) ?>">
                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                  </button>
                  <button class="icono icono-rojo" type="button" title="Eliminar" aria-label="Eliminar <?= h($a['nombre']) ?>"
                          data-eliminar data-id="<?= (int)$a['id'] ?>" data-nombre="<?= h($a['nombre']) ?>">
                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 14h10l1-14"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <footer class="pie-tabla">
        <span class="conteo">Mostrando <?= $offset + 1 ?>–<?= $offset + count($articulos) ?> de <?= $totalFiltrado ?></span>
        <?php if ($totalPaginas > 1): ?>
        <nav class="paginas" aria-label="Paginación">
          <?php if ($pagina > 1): ?><a class="pag" href="<?= enlace_pagina($filtros, $pagina - 1) ?>" aria-label="Página anterior">‹</a><?php endif; ?>
          <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <a class="pag <?= $i === $pagina ? 'activa' : '' ?>" href="<?= enlace_pagina($filtros, $i) ?>" <?= $i === $pagina ? 'aria-current="page"' : '' ?>><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($pagina < $totalPaginas): ?><a class="pag" href="<?= enlace_pagina($filtros, $pagina + 1) ?>" aria-label="Página siguiente">›</a><?php endif; ?>
        </nav>
        <?php endif; ?>
      </footer>
    <?php endif; ?>
  </section>

</main>

<!-- Ventana: crear / editar -->
<dialog id="dlg-articulo" class="ventana vidrio" aria-labelledby="dlg-titulo">
  <form method="post" action="index.php" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= h($token) ?>">
    <input type="hidden" name="accion" id="f-accion" value="crear">
    <input type="hidden" name="id" id="f-id" value="">
    <?php foreach ($filtros as $k => $v): ?><input type="hidden" name="<?= h($k) ?>" value="<?= h((string)$v) ?>"><?php endforeach; ?>

    <h2 id="dlg-titulo">Nuevo artículo</h2>

    <label class="campo"><span>Nombre</span>
      <input type="text" name="nombre" id="f-nombre" maxlength="150" required placeholder="Ej. Teclado inalámbrico">
    </label>
    <div class="fila">
      <label class="campo"><span>Marca</span>
        <input type="text" name="marca" id="f-marca" maxlength="100" required list="lista-marcas" placeholder="Ej. Logitech">
      </label>
      <label class="campo"><span>Unidades</span>
        <input type="number" name="unidades" id="f-unidades" min="0" max="1000000" step="1" required value="0" inputmode="numeric">
      </label>
    </div>
    <label class="campo"><span>Ubicación</span>
      <input type="text" name="ubicacion" id="f-ubicacion" maxlength="150" required list="lista-ubicaciones" placeholder="Ej. Bodega A · Estante 3">
    </label>

    <datalist id="lista-marcas"><?php foreach ($marcas as $m): ?><option value="<?= h($m) ?>"><?php endforeach; ?></datalist>
    <datalist id="lista-ubicaciones"><?php foreach ($ubicaciones as $u2): ?><option value="<?= h($u2) ?>"><?php endforeach; ?></datalist>

    <div class="botones">
      <button type="button" class="btn btn-gris" data-cerrar>Cancelar</button>
      <button type="submit" class="btn btn-verde" id="f-guardar">Guardar artículo</button>
    </div>
  </form>
</dialog>

<!-- Ventana: confirmar eliminación -->
<dialog id="dlg-eliminar" class="ventana ventana-chica vidrio" aria-labelledby="del-titulo">
  <form method="post" action="index.php">
    <input type="hidden" name="csrf" value="<?= h($token) ?>">
    <input type="hidden" name="accion" value="eliminar">
    <input type="hidden" name="id" id="del-id" value="">
    <?php foreach ($filtros as $k => $v): ?><input type="hidden" name="<?= h($k) ?>" value="<?= h((string)$v) ?>"><?php endforeach; ?>
    <h2 id="del-titulo">¿Eliminar este artículo?</h2>
    <p>Vas a eliminar <strong id="del-nombre"></strong>. Esta acción no se puede deshacer.</p>
    <div class="botones">
      <button type="button" class="btn btn-gris" data-cerrar>Conservar</button>
      <button type="submit" class="btn btn-rojo">Eliminar artículo</button>
    </div>
  </form>
</dialog>

<?php if ($reabrir): ?>
<script id="reabrir" type="application/json"><?= json_encode($reabrir, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
<script src="assets/app.js"></script>
</body>
</html>
