(function () {
  'use strict';
  var dlgArt = document.getElementById('dlg-articulo');
  var dlgDel = document.getElementById('dlg-eliminar');
  var $ = function (id) { return document.getElementById(id); };

  function abrirArticulo(datos) {
    var editar = datos && datos.accion === 'editar';
    $('f-accion').value = editar ? 'editar' : 'crear';
    $('f-id').value = editar ? datos.id : '';
    $('dlg-titulo').textContent = editar ? 'Editar artículo' : 'Nuevo artículo';
    $('f-guardar').textContent = editar ? 'Guardar cambios' : 'Guardar artículo';
    $('f-nombre').value = (datos && datos.nombre) || '';
    $('f-marca').value = (datos && datos.marca) || '';
    $('f-unidades').value = datos && datos.unidades !== undefined && datos.unidades !== '' ? datos.unidades : 0;
    $('f-ubicacion').value = (datos && datos.ubicacion) || '';
    dlgArt.showModal();
    $('f-nombre').focus();
  }

  document.addEventListener('click', function (e) {
    var t = e.target.closest('button, a');
    if (!t) { return; }

    if (t.hasAttribute('data-abrir-crear')) { abrirArticulo(null); }

    if (t.hasAttribute('data-editar')) {
      abrirArticulo({
        accion: 'editar', id: t.dataset.id, nombre: t.dataset.nombre,
        marca: t.dataset.marca, unidades: t.dataset.unidades, ubicacion: t.dataset.ubicacion
      });
    }

    if (t.hasAttribute('data-eliminar')) {
      $('del-id').value = t.dataset.id;
      $('del-nombre').textContent = '"' + t.dataset.nombre + '"';
      dlgDel.showModal();
    }

    if (t.hasAttribute('data-cerrar')) { t.closest('dialog').close(); }

    if (t.hasAttribute('data-cerrar-aviso')) { t.closest('[data-aviso]').remove(); }
  });

  // Cerrar al hacer clic en el fondo oscuro
  [dlgArt, dlgDel].forEach(function (d) {
    d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });
  });

  // Reabrir el formulario con los datos escritos si hubo un error de validación
  var r = $('reabrir');
  if (r) {
    try { abrirArticulo(JSON.parse(r.textContent)); } catch (err) { /* sin acción */ }
  }

  // El aviso de éxito se oculta solo
  var aviso = document.querySelector('.aviso-ok[data-aviso]');
  if (aviso) { setTimeout(function () { aviso.remove(); }, 5000); }
})();
