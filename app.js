(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  const esc = s => String(s).replace(/[&<>"']/g, c => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  }[c]));

  const post = (a, d = {}) =>
    fetch(BASE + 'api.php', {
      method: 'POST',
      body: new URLSearchParams({ action: a, csrf: CSRF, ...d })
    }).then(r => r.json());

  const get = (a, d = {}) =>
    fetch(BASE + 'api.php?' + new URLSearchParams({
      action: a,
      ...d
    })).then(r => r.json());


  /* القوائم المنسدلة والقائمة الجانبية */

  $('#burger')?.addEventListener('click', () => {
    $('#links').classList.toggle('open');
  });

  $$('[data-dd]').forEach(b => b.addEventListener('click', e => {
    e.stopPropagation();

    const m = $('#' + b.dataset.dd);
    const wasOpen = !m.hidden;

    $$('.dd').forEach(x => x.hidden = true);
    m.hidden = wasOpen;

    if (b.dataset.dd === 'ddNotes' && !wasOpen) {
      post('read_notes').then(() => $('.bell-n')?.remove());
    }
  }));

  document.addEventListener('click', () => {
    $$('.dd').forEach(x => x.hidden = true);
  });

  setTimeout(() => $('.toast')?.remove(), 4500);


  /* اختيار المحافظة */

  let curPicker = null;

  const loadAreas = (gov, form) => {
    const s = $('select[name=area]', form || document);
    if (!s) return;

    get('areas', { gov }).then(a => {
      s.innerHTML =
        '<option value="">كل المناطق</option>' +
        a.map(x =>
          `<option value="${x.id}">${esc(x.name)}</option>`
        ).join('');
    });
  };

  $$('[data-open-gov]').forEach(b => {
    b.addEventListener('click', () => {
      curPicker = b;
      $('#govModal').hidden = false;
    });
  });

  $('#govModal')?.addEventListener('click', e => {

    if (e.target.matches('[data-close],#govModal')) {
      $('#govModal').hidden = true;
      return;
    }

    const g = e.target.closest('[data-gov]');
    if (!g || !curPicker) return;

    const f = curPicker.closest('.field');
    const form = curPicker.closest('form');

    $('input[type=hidden]', f).value = g.dataset.gov;
    $('.pick-label', f).textContent = g.dataset.name;

    $('#govModal').hidden = true;

    if (curPicker.dataset.go) {
      location.href = BASE + 'crafts.php?gov=' + g.dataset.gov;
      return;
    }

    if (curPicker.dataset.submit) {
      const a = $('select[name=area]', form);
      if (a) a.value = '';
      form.submit();
      return;
    }

    loadAreas(g.dataset.gov, form);
  });

  $$('select[name=gov]').forEach(s => {
    s.addEventListener('change', () => {
      loadAreas(s.value, s.closest('form'));
    });
  });

  $('#changeArea')?.addEventListener('click', () => {
    const a = $('#ff select[name=area]');
    a?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    a?.focus();
  });


  /* الحرفة → الخدمات */

  $('#catSel')?.addEventListener('change', e => {

    get('services', { cat: e.target.value }).then(a => {

      $('#svcSel').innerHTML =
        '<option value="">اختر الخدمة</option>' +
        a.map(x =>
          `<option value="${x.id}">${esc(x.name)}</option>`
        ).join('');

    });
  });


  /* المفضلة */

  document.addEventListener('click', e => {

    const h = e.target.closest('[data-fav]');
    if (!h) return;

    e.preventDefault();

    post('fav', { id: h.dataset.fav }).then(r => {

      if (r.login) {
        location.href = BASE + 'login.php';
      } else {
        h.classList.toggle('on', r.on);
      }

    });
  });


  /* المقارنة */

  const CK = 'sahl_cmp';

  const getC = () => {
    try {
      return JSON.parse(localStorage[CK] || '[]');
    } catch (e) {
      return [];
    }
  };

  const paintC = () => {

    const c = getC();

    $$('.btn-compare').forEach(b => {
      b.classList.toggle('on', c.includes(b.dataset.id));
    });

    const bar = $('#cmpBar');
    if (!bar) return;

    bar.hidden = !c.length;
    $('#cmpN').textContent = c.length;
    $('#cmpGo').href = BASE + 'compare.php?ids=' + c.join(',');
  };

  document.addEventListener('click', e => {

    const b = e.target.closest('.btn-compare');

    if (b) {

      let c = getC();
      const id = b.dataset.id;

      if (c.includes(id)) {
        c = c.filter(x => x !== id);
      } else if (c.length >= 4) {
        return alert('يمكن مقارنة 4 حرفيين كحد أقصى');
      } else {
        c.push(id);
      }

      localStorage[CK] = JSON.stringify(c);
      paintC();
    }

    if (e.target.closest('#cmpClear')) {
      localStorage[CK] = '[]';
      paintC();
    }

  });

  paintC();


  /* الفلاتر */

  $$('[data-auto]').forEach(i => {
    i.addEventListener('change', () => $('#ff').submit());
  });

  $('#pr')?.addEventListener('input', e => {
    $('#pmax').value = e.target.value;
  });

  $('#pr')?.addEventListener('change', () => {
    $('#ff').submit();
  });

  $('#distSel')?.addEventListener('change', e => {

    if (!e.target.value) {
      return $('#ff').submit();
    }

    navigator.geolocation?.getCurrentPosition(

      p => {
        $('#lat').value = p.coords.latitude;
        $('#lng').value = p.coords.longitude;
        $('#ff').submit();
      },

      () => {
        alert('فعّل صلاحية الموقع لاستخدام فلتر المسافة');
        e.target.value = '';
      }

    );
  });

  $$('[data-view]').forEach(b => {

    b.addEventListener('click', () => {

      $('#cards').className = 'cards ' + b.dataset.view;

      $$('[data-view]').forEach(x => {
        x.classList.toggle('on', x === b);
      });

    });

  });


  /* مشاهدات الخدمات */

  $$('.svc-row[data-svc]').forEach(r => {

    r.addEventListener(
      'click',
      () => post('svc_view', { id: r.dataset.svc }),
      { once: true }
    );

  });


  /* خريطة عرض موقع الحرفي للعميل */

  if ($('#map') && window.L) {

    const pts = (window.MAP_POINTS || []).filter(p => {

      return Number.isFinite(Number(p.lat)) &&
             Number.isFinite(Number(p.lng)) &&
             Number(p.lat) >= -90 &&
             Number(p.lat) <= 90 &&
             Number(p.lng) >= -180 &&
             Number(p.lng) <= 180 &&
             !(Number(p.lat) === 0 && Number(p.lng) === 0);

    });

    if (pts.length) {

      const map = L.map('map', {
        scrollWheelZoom: false
      }).setView(
        window.MAP_CENTER || [Number(pts[0].lat), Number(pts[0].lng)],
        window.MAP_ZOOM || 13
      );

      L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
          attribution: '© OpenStreetMap'
        }
      ).addTo(map);

      pts.forEach(p => {

        L.marker([Number(p.lat), Number(p.lng)])
          .addTo(map)
          .bindPopup(
            `<a href="${BASE}craftsman.php?id=${p.id}">
              <b>${esc(p.name)}</b>
            </a><br>${esc(p.cat)}`
          );

      });

      if (pts.length > 1 && !window.MAP_CENTER) {
        map.fitBounds(
          pts.map(p => [Number(p.lat), Number(p.lng)]),
          { padding: [30, 30] }
        );
      }

    }
  }


  /* خريطة تحديد موقع الصنايعي */

  const locationMapElement = $('#craftsman-location-map');

  if (locationMapElement && window.L) {

    const latInput = $('#craftsman-lat');
    const lngInput = $('#craftsman-lng');
    const status = $('#location-status');
    const locateBtn = $('#get-my-location');

    const initialLat = parseFloat(latInput.value);
    const initialLng = parseFloat(lngInput.value);

    const hasInitialLocation =
      latInput.value.trim() !== '' &&
      lngInput.value.trim() !== '' &&
      Number.isFinite(initialLat) &&
      Number.isFinite(initialLng) &&
      initialLat >= -90 &&
      initialLat <= 90 &&
      initialLng >= -180 &&
      initialLng <= 180 &&
      !(initialLat === 0 && initialLng === 0);

    const map = L.map(locationMapElement, {
      scrollWheelZoom: false
    }).setView(
      hasInitialLocation
        ? [initialLat, initialLng]
        : [26.8, 30.8],
      hasInitialLocation ? 15 : 6
    );

    L.tileLayer(
      'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      {
        attribution: '© OpenStreetMap contributors'
      }
    ).addTo(map);

    let marker = null;

    function selectLocation(lat, lng) {

      lat = Number(lat);
      lng = Number(lng);

      if (
        !Number.isFinite(lat) ||
        !Number.isFinite(lng) ||
        lat < -90 ||
        lat > 90 ||
        lng < -180 ||
        lng > 180
      ) {
        status.textContent = 'الإحداثيات غير صحيحة.';
        return;
      }

      latInput.value = lat.toFixed(7);
      lngInput.value = lng.toFixed(7);

      if (marker) {
        marker.setLatLng([lat, lng]);
      } else {
        marker = L.marker([lat, lng]).addTo(map);
      }

      status.textContent =
        'تم اختيار الموقع. اضغط حفظ التعديلات لتأكيده.';

      map.setView([lat, lng], 16);
    }

    /* عرض الموقع المحفوظ */

    if (hasInitialLocation) {
      selectLocation(initialLat, initialLng);
    }

    /* اختيار الموقع بالنقر على الخريطة */

    map.on('click', event => {

      selectLocation(
        event.latlng.lat,
        event.latlng.lng
      );

    });

    /* تحديد الموقع تلقائياً */

    locateBtn?.addEventListener('click', () => {

      if (!navigator.geolocation) {
        status.textContent =
          'المتصفح لا يدعم تحديد الموقع.';
        return;
      }

      status.textContent = 'جاري تحديد موقعك...';

      navigator.geolocation.getCurrentPosition(

        position => {

          selectLocation(
            position.coords.latitude,
            position.coords.longitude
          );

        },

        error => {

          if (error.code === error.PERMISSION_DENIED) {
            status.textContent =
              'لم تسمح للمتصفح بتحديد موقعك. يمكنك اختيار الموقع يدوياً.';
          } else {
            status.textContent =
              'تعذر تحديد الموقع. اختاره يدوياً من الخريطة.';
          }

        },

        {
          enableHighAccuracy: true,
          timeout: 15000,
          maximumAge: 0
        }

      );

    });

    /* ضبط حجم الخريطة بعد ظهورها */

    setTimeout(() => {
      map.invalidateSize();
    }, 300);

  }


  /* الشات */

  const chat = $('#chat');

  if (chat && +chat.dataset.to) {

    const to = +chat.dataset.to;
    const box = $('#msgs');
    const seen = new Set();

    let last = +chat.dataset.last || 0;

    const add = m => {

      if (seen.has(m.id)) return;

      seen.add(m.id);

      const d = document.createElement('div');

      d.className = 'msg' + (m.mine ? ' mine' : '');

      d.innerHTML = '<p></p><time></time>';

      d.firstChild.textContent = m.body;
      d.lastChild.textContent = m.time;

      box.appendChild(d);

      last = Math.max(last, m.id);

      box.scrollTop = box.scrollHeight;
    };

    box.scrollTop = box.scrollHeight;

    $('#sendForm').addEventListener('submit', e => {

      e.preventDefault();

      const i = $('#msgIn');
      const b = i.value.trim();

      if (!b) return;

      i.value = '';

      post('send', { to, body: b }).then(r => {
        if (r.msg) add(r.msg);
      });

    });

    setInterval(() => {

      get('msgs', {
        with: to,
        after: last
      }).then(a => {

        if (Array.isArray(a)) {
          a.forEach(add);
        }

      });

    }, 4000);

  }

})();