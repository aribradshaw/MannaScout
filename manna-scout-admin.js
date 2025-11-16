(function ($) {
  $(document).ready(function () {
    // Media picker for photo URL
    $('.ms-select-image').on('click', function (e) {
      e.preventDefault();
      const frame = wp.media({
        title: 'Select or Upload Image',
        button: { text: 'Use this image' },
        multiple: false,
      });
      frame.on('select', function () {
        const attachment = frame.state().get('selection').first().toJSON();
        $('#ms_photo_url').val(attachment.url);
      });
      frame.open();
    });

    // Auto-fill slug from name if empty
    const $name = $('#ms_name');
    const $slug = $('#ms_slug');
    $name.on('input', function () {
      if (!$slug.val()) {
        const s = ($name.val() || '')
          .toString()
          .trim()
          .toLowerCase()
          .normalize('NFD')
          .replace(/\p{Diacritic}/gu, '')
          .replace(/[^a-z0-9]+/g, '-')
          .replace(/^-+|-+$/g, '');
        $slug.val(s);
      }
    });

    // Gallery
    function readGallery() {
      try {
        return JSON.parse($('#ms_gallery').val() || '[]');
      } catch (e) { return []; }
    }
    function writeGallery(arr) {
      $('#ms_gallery').val(JSON.stringify(arr));
    }
    function refreshThumbs() {
      const arr = readGallery();
      const $wrap = $('.ms-gallery-thumbs');
      $wrap.empty();
      arr.forEach(function (url) {
        const $el = $([
          '<div class="ms-thumb" data-url="' + url + '">',
          '  <img src="' + url + '" />',
          '  <button type="button" class="button-link ms-thumb-remove" aria-label="Remove">×</button>',
          '</div>'
        ].join(''));
        $wrap.append($el);
      });
    }

    $('.ms-add-gallery').on('click', function () {
      const frame = wp.media({
        title: 'Select Images',
        button: { text: 'Add selected' },
        multiple: true,
        library: { type: 'image' }
      });
      frame.on('select', function () {
        const selection = frame.state().get('selection');
        const urls = [];
        selection.each(function (att) {
          const a = att.toJSON();
          if (a && a.url) urls.push(a.url);
        });
        const current = readGallery();
        const merged = current.concat(urls);
        writeGallery(merged);
        refreshThumbs();
      });
      frame.open();
    });

    $(document).on('click', '.ms-thumb-remove', function () {
      const url = $(this).closest('.ms-thumb').data('url');
      const arr = readGallery();
      const next = arr.filter(function (u) { return u !== url; });
      writeGallery(next);
      refreshThumbs();
    });

    $('.ms-clear-gallery').on('click', function () {
      writeGallery([]);
      refreshThumbs();
    });
  });
})(jQuery);


