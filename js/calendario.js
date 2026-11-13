(() => {

const NEXUS_CALENDAR_INIT = async () => {
  'use strict';

  // Evita dos inicializaciones simultáneas (DOMContentLoaded + navegación SPA).
  // El estado se marca como listo al final, no antes de cargar los datos,
  // para que un fallo transitorio no deje el calendario bloqueado hasta F5.
  const grid = document.getElementById('calendarGrid');
  if (!grid) return;
  if (grid.dataset.nexusCalendarInitializing === '1' || grid.dataset.nexusCalendarInitialized === '1') return;
  grid.dataset.nexusCalendarInitializing = '1';
  const title = document.getElementById('monthTitle');
  const list = document.getElementById('eventList');
  const account = document.getElementById('calendarAccount');

  let view = new Date();
  view.setDate(1);

  let events = [];
  let me = { logged_in: false };

  const pad = n => String(n).padStart(2, '0');

  const iso = d =>
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[c]));
  }

  /*
   * ============================================================
   * RAZAS, CLASES Y ROLES DE WOW FOREVER
   * ============================================================
   */

  // Se carga desde el servidor para que Admin → Facción controle toda la web.
  let faction = 'horda';
  let factionLabel = 'Horda';
  let races = [];
  let raceClasses = {};
  let classes = [];

  const classRoles = {
    'Guerrero': [
      'Tanque',
      'DPS'
    ],

        'Cazador': [
      'DPS'
    ],

    'Pícaro': [
      'DPS'
    ],

    'Sacerdote': [
      'DPS',
      'Healer'
    ],

    'Chamán': [
      'DPS',
      'Healer'
    ],

    'Mago': [
      'DPS'
    ],

    'Brujo': [
      'DPS'
    ],

    'Druida': [
      'Tanque',
      'DPS',
      'Healer'
    ],

    'Paladín': [
      'Tanque',
      'DPS',
      'Healer'
    ]
  };

  const signupEndpoint = '/api/event-signup.php';
  const calendarUrl = '/calendario.php';
  const discordLoginUrl = '/auth/discord.php';

  function discordLoginUrlWithReturn() {
    return (
      discordLoginUrl +
      '?return_to=' +
      encodeURIComponent(calendarUrl)
    );
  }

  /*
   * ============================================================
   * JSON SEGURO
   * ============================================================
   *
   * El PHP debe devolver JSON.
   * Si accidentalmente devuelve HTML/PHP error, mostramos
   * un mensaje entendible en lugar de:
   * "Unexpected token <"
   */

  async function readJson(response) {

    const text = await response.text();

    let data;

    try {
      data = JSON.parse(text);
    } catch (error) {

      console.error(
        'Respuesta no válida del servidor:',
        text
      );

      throw new Error(
        'El servidor ha devuelto un error. Revisa la respuesta de PHP.'
      );
    }

    return data;
  }

  /*
   * ============================================================
   * CARGAR DATOS
   * ============================================================
   */

  async function loadData() {

    try {

      const [configResponse, evResponse, meResponse] = await Promise.all([

        fetch('./api/faction.php?cb=' + Date.now(), { cache: 'no-store', credentials: 'same-origin' }),

        fetch(
          './api/events.php?cb=' + Date.now(),
          {
            cache: 'no-store',
            credentials: 'same-origin'
          }
        ),

        fetch(
          './api/me.php?cb=' + Date.now(),
          {
            cache: 'no-store',
            credentials: 'same-origin'
          }
        )

      ]);

      const factionData = await readJson(configResponse);
      faction = factionData.faction || 'horda';
      factionLabel = factionData.label || 'Horda';
      raceClasses = factionData.races || {};
      races = Object.keys(raceClasses);
      classes = [...new Set(Object.values(raceClasses).flat())];

      const evData =
        await readJson(evResponse);

      const meData =
        await readJson(meResponse);

      events = Array.isArray(evData)
        ? evData
        : (
            Array.isArray(evData.events)
              ? evData.events
              : []
          );

      me =
        meData &&
        typeof meData === 'object'
          ? meData
          : {
              logged_in: false
            };

      console.log(
        'NEXUS calendario - sesión:',
        me
      );

      console.log(
        'NEXUS calendario - eventos:',
        events
      );

    } catch (error) {

      console.error(
        'Error cargando calendario:',
        error
      );

      events = [];

      me = {
        logged_in: false
      };
    }
  }

  /*
   * ============================================================
   * SESIÓN
   * ============================================================
   */

  async function refreshSession() {

    try {

      const response =
        await fetch(
          './api/me.php?cb=' + Date.now(),
          {
            cache: 'no-store',
            credentials: 'same-origin'
          }
        );

      const data =
        await readJson(response);

      if (
        data &&
        typeof data === 'object'
      ) {
        me = data;
      }

      updateAccount();

      return !!me.logged_in;

    } catch (error) {

      console.error(
        'Error comprobando sesión:',
        error
      );

      updateAccount();
      return false;
    }
  }

  function updateAccount() {

    if (!account) {
      return;
    }

    account.innerHTML = me.logged_in
      ? `
        <span>
          Conectado como
          <strong>
            ${esc(me.username || 'Discord')}
          </strong>
        </span>

        <a
          class="btn ghost"
          href="/panel.php"
        >
          Mi cuenta
        </a>
      `
      : `
        <span class="muted">
          Para apuntarte a un evento, vincula tu cuenta de Discord.
        </span>

        <a
          class="btn primary"
          href="${discordLoginUrlWithReturn()}"
        >
          Entrar con Discord
        </a>
      `;
  }

  /*
   * ============================================================
   * BUSCAR EVENTO
   * ============================================================
   */

  function getEvent(id) {

    return events.find(
      e => Number(e.id) === Number(id)
    );
  }

  /*
   * ============================================================
   * CREAR OPCIONES DE SELECT
   * ============================================================
   */

  function fillSelect(
    select,
    values,
    selectedValue,
    placeholder
  ) {

    if (!select) {
      return;
    }

    select.innerHTML = '';

    const first =
      document.createElement('option');

    first.value = '';
    first.textContent = placeholder;

    select.appendChild(first);

    values.forEach(value => {

      const option =
        document.createElement('option');

      option.value = value;
      option.textContent = value;

      if (
        String(value) ===
        String(selectedValue || '')
      ) {
        option.selected = true;
      }

      select.appendChild(option);
    });
  }

  /*
   * ============================================================
   * ACTUALIZAR ROLES SEGÚN CLASE
   * ============================================================
   */

  function updateRoleSelect(
    classSelect,
    roleSelect,
    selectedRole = ''
  ) {

    if (!classSelect || !roleSelect) {
      return;
    }

    const selectedClass =
      classSelect.value || '';

    const availableRoles =
      classRoles[selectedClass] || [];

    fillSelect(
      roleSelect,
      availableRoles,
      selectedRole,
      selectedClass
        ? 'Selecciona tu rol'
        : 'Selecciona primero tu clase'
    );

    roleSelect.disabled =
      availableRoles.length === 0;

    /*
     * Si solamente existe un rol posible,
     * lo seleccionamos automáticamente.
     */

    if (
      availableRoles.length === 1
    ) {

      roleSelect.value =
        availableRoles[0];
    }
  }

  /*
   * ============================================================
   * HTML DEL MODAL
   * ============================================================
   */

  function signupModal(e) {

    return `
      <div class="signup-modal-backdrop" id="signupModal">
        <div class="signup-modal card" role="dialog" aria-modal="true" aria-labelledby="signupModalTitle">
          <button type="button" class="signup-close" aria-label="Cerrar">×</button>

          <h3 id="signupModalTitle">
            ${e.user_registered ? 'Editar inscripción' : 'Apuntarte'} a ${esc(e.title || 'el evento')}
          </h3>

          <p class="muted">
            Selecciona el personaje con el que participarás. Solo aparecerán los personajes que tienes registrados en <strong>MI PANEL</strong>.
          </p>

          <form id="signupForm" autocomplete="off">
            <input type="hidden" id="signupEventId" name="event_id" value="${esc(e.id)}">

            <div class="signup-field">
              <label for="signupCharacter">Personaje</label>
              <select id="signupCharacter" name="character_id" required>
                <option value="">Cargando tus personajes…</option>
              </select>
              <small class="muted">La raza, clase y rol se obtienen automáticamente del personaje que has creado en MI PANEL.</small>
            </div>

            <div class="admin-actions" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;">
              <button class="btn primary" type="submit">
                ${e.user_registered ? 'Guardar cambios' : 'Confirmar inscripción'}
              </button>
              ${e.user_registered ? `<button type="button" class="btn ghost" id="removeSignup">Desapuntarme</button>` : ''}
            </div>
          </form>
        </div>
      </div>
    `;
  }

  /*
   * ============================================================
   * ABRIR MODAL
   * ============================================================
   */

  async function openSignupModal(e) {

    const loggedIn =
      await refreshSession();

    if (!loggedIn) {
      const go = discordLoginUrlWithReturn();
      if (confirm('Necesitas iniciar sesión con Discord para apuntarte. ¿Quieres iniciar sesión ahora?')) {
        window.location.assign(go);
      }
      return;
    }

    /*
     * Eliminar modal anterior.
     */

    const oldModal =
      document.getElementById('signupModal');

    if (oldModal) {
      oldModal.remove();
    }

    /*
     * Cargar inscripción actual.
     */

    try {

      const response =
        await fetch(
          `${signupEndpoint}?event_id=${encodeURIComponent(e.id)}&cb=${Date.now()}`,
          {
            method: 'GET',
            cache: 'no-store',
            credentials: 'same-origin'
          }
        );

      const data =
        await readJson(response);

      if (response.status === 401) {

        location.href =
          discordLoginUrlWithReturn();

        return;
      }

      if (!response.ok) {

        throw new Error(
          data.error ||
          'No se pudo consultar la inscripción.'
        );
      }

      if (data) {

        e.user_registered =
          !!data.registered;

        e.user_race =
          data.race || '';

        e.user_class =
          data.class_name || '';

        e.user_role =
          data.role || '';

        if (
          typeof data.count !==
          'undefined'
        ) {

          e.attendee_count =
            Number(data.count);
        }
      }

    } catch (error) {

      console.error(
        'No se pudo cargar la inscripción:',
        error
      );

      alert(
        error.message ||
        'No se pudo cargar la inscripción.'
      );

      return;
    }

    /*
     * Insertar modal.
     */

    document.body.insertAdjacentHTML(
      'beforeend',
      signupModal(e)
    );

    const modal =
      document.getElementById('signupModal');

    if (!modal) {

      console.error(
        'NEXUS: no se pudo crear signupModal.'
      );

      return;
    }

    const form =
      document.getElementById('signupForm');

    if (!form) {

      console.error(
        'NEXUS: no se pudo crear signupForm.'
      );

      return;
    }

    /*
     * ========================================================
     * CAMPOS
     * ========================================================
     */

    const eventIdInput = document.getElementById('signupEventId');
    const characterSelect = document.getElementById('signupCharacter');

    if (eventIdInput) eventIdInput.value = String(e.id);

    /* Cargar personajes creados por este usuario en MI PANEL. */
    try {
      const charactersResponse = await fetch(
        `/api/my-characters.php?cb=${Date.now()}`,
        { cache: 'no-store', credentials: 'same-origin' }
      );
      const charactersData = await readJson(charactersResponse);

      if (!charactersResponse.ok) {
        throw new Error(charactersData.error || 'No se pudieron cargar tus personajes.');
      }

      const characters = Array.isArray(charactersData.characters)
        ? charactersData.characters
        : [];

      if (!characterSelect) throw new Error('No se encontró el selector de personajes.');

      characterSelect.innerHTML = '';
      const first = document.createElement('option');
      first.value = '';
      first.textContent = characters.length ? 'Selecciona tu personaje' : 'No tienes personajes registrados';
      characterSelect.appendChild(first);

      characters.forEach(character => {
        const option = document.createElement('option');
        option.value = String(character.id);
        option.textContent = `${character.name} — ${character.class_name} — ${character.role}`;
        option.dataset.race = character.race || '';
        option.dataset.className = character.class_name || '';
        option.dataset.role = character.role || '';
        if (e.user_character_id && Number(e.user_character_id) === Number(character.id)) {
          option.selected = true;
        }
        characterSelect.appendChild(option);
      });

      characterSelect.disabled = characters.length === 0;
    } catch (error) {
      console.error('NEXUS: no se pudieron cargar los personajes:', error);
      if (characterSelect) {
        characterSelect.innerHTML = '<option value="">No se pudieron cargar tus personajes</option>';
        characterSelect.disabled = true;
      }
      alert(error.message || 'No se pudieron cargar tus personajes.');
      return;
    }

    const closeButton =
      modal.querySelector(
        '.signup-close'
      );

    const removeButton =
      document.getElementById(
        'removeSignup'
      );

    /*
     * ========================================================
     * CERRAR
     * ========================================================
     */

    const close = () => {

      if (modal) {
        modal.remove();
      }

      document.removeEventListener(
        'keydown',
        escHandler
      );
    };

    if (closeButton) {

      closeButton.addEventListener(
        'click',
        close
      );
    }

    modal.addEventListener(
      'click',
      event => {

        if (
          event.target === modal
        ) {
          close();
        }
      }
    );

    const escHandler = event => {

      if (event.key === 'Escape') {
        close();
      }
    };

    document.addEventListener(
      'keydown',
      escHandler
    );

    /*
     * ========================================================
     * DESAPUNTARSE
     * ========================================================
     */

    if (removeButton) {

      removeButton.addEventListener(
        'click',
        async () => {

          if (
            !confirm(
              '¿Quieres quitarte de este evento?'
            )
          ) {
            return;
          }

          removeButton.disabled = true;

          try {

            /*
             * Enviamos el event_id también
             * en la URL.
             */

            const response =
              await fetch(
                `${signupEndpoint}?event_id=${encodeURIComponent(e.id)}`,
                {
                  method: 'DELETE',
                  cache: 'no-store',
                  credentials: 'same-origin'
                }
              );

            const data =
              await readJson(response);

            if (
              response.status === 401
            ) {

              location.href =
                discordLoginUrlWithReturn();

              return;
            }

            if (!response.ok) {

              throw new Error(
                data.error ||
                'No se pudo cancelar la inscripción.'
              );
            }

            e.user_registered = false;
            e.user_race = '';
            e.user_class = '';
            e.user_role = '';

            if (
              typeof data.count !==
              'undefined'
            ) {

              e.attendee_count =
                Number(data.count);

            } else if (
              Number(e.attendee_count) > 0
            ) {

              e.attendee_count =
                Number(e.attendee_count) - 1;
            }

            close();

            render();

          } catch (error) {

            console.error(
              'Error cancelando inscripción:',
              error
            );

            alert(
              error.message ||
              'No se pudo cancelar la inscripción.'
            );

          } finally {

            removeButton.disabled = false;
          }
        }
      );
    }

    /*
     * ========================================================
     * ENVIAR INSCRIPCIÓN
     * ========================================================
     */

    form.addEventListener(
      'submit',
      async event => {

        event.preventDefault();

        const submitButton =
          form.querySelector(
            'button[type="submit"]'
          );

        if (!submitButton) {
          return;
        }

        /*
         * Validar evento.
         */

        const eventId =
          Number(
            eventIdInput?.value ||
            e.id ||
            0
          );

        if (!eventId) {

          alert(
            'Evento no válido.'
          );

          return;
        }

        /*
         * Validar campos.
         */

        submitButton.disabled = true;

        try {

          /*
           * ==================================================
           * FORM DATA
           * ==================================================
           *
           * AQUÍ está la corrección importante:
           *
           * formData.set('event_id', String(eventId))
           *
           * Esto garantiza que PHP recibe el ID.
           */

          const formData =
            new FormData(form);

          formData.set(
            'event_id',
            String(eventId)
          );

          if (!characterSelect || !characterSelect.value) {
            alert('Selecciona el personaje con el que participarás.');
            submitButton.disabled = false;
            return;
          }

          formData.set('character_id', characterSelect.value);

          console.log('NEXUS inscripción - enviando:', {
            event_id: formData.get('event_id'),
            character_id: formData.get('character_id')
          });

          /*
           * ==================================================
           * POST
           * ==================================================
           */

          const response =
            await fetch(
              signupEndpoint,
              {
                method: 'POST',
                body: formData,
                cache: 'no-store',
                credentials: 'same-origin'
              }
            );

          const data =
            await readJson(response);

          if (
            response.status === 401
          ) {

            location.href =
              discordLoginUrlWithReturn();

            return;
          }

          if (!response.ok) {

            throw new Error(
              data.error ||
              'No se pudo completar la inscripción.'
            );
          }

          /*
           * ==================================================
           * ACTUALIZAR EVENTO
           * ==================================================
           */

          e.user_registered = true;

          e.user_race = data.race || '';
          e.user_class = data.class_name || '';
          e.user_role = data.role || '';
          e.user_character_id = data.character_id || '';

          if (
            typeof data.count !==
            'undefined'
          ) {

            e.attendee_count =
              Number(data.count);
          }

          // La inscripción web se guarda aunque Discord falle, pero
          // mostramos el resultado real de la sincronización.
          if (data.discord_sync && data.discord_sync.ok === false && data.discord_sync.error) {
            console.error('NEXUS: fallo sincronizando evento con Discord:', data.discord_sync.error);
            alert('Te has apuntado al evento, pero no se pudo actualizar Discord: ' + data.discord_sync.error);
          }

          /*
           * Cerrar y volver a pintar.
           */

          close();

          render();

        } catch (error) {

          console.error(
            'Error en inscripción:',
            error
          );

          alert(
            error.message ||
            'No se pudo completar la inscripción.'
          );

        } finally {

          submitButton.disabled = false;
        }
      }
    );
  }

  /*
   * ============================================================
   * BOTÓN DE INSCRIPCIÓN
   * ============================================================
   */

  function signupButton(e) {

    const id =
      Number(e.id);

    if (!id) {
      return '';
    }

    const registered =
      !!e.user_registered;

    let detail = '';

    if (
      registered &&
      e.user_race &&
      e.user_class
    ) {

      detail =
        ` · ${esc(e.user_race)} · ${esc(e.user_class)}`;

      if (e.user_role) {

        detail +=
          ` · ${esc(e.user_role)}`;
      }
    }

    const count =
      Number(e.attendee_count || 0) > 0
        ? ` · ${Number(e.attendee_count)}`
        : '';

    return `
      <button
        type="button"
        class="event-signup btn ${
          registered
            ? 'ghost'
            : 'primary'
        }"
        data-id="${id}"
      >
        ${
          registered
            ? '✓ Apuntado'
            : 'Apuntarme'
        }${count}${detail}
      </button>
    `;
  }

  /*
   * ============================================================
   * BOTÓN DEL EVENTO
   * ============================================================
   */

  function eventAction(e) {

    const id =
      Number(e.id);

    if (!id) {
      return '';
    }

    const registered =
      !!e.user_registered;

    return `
      <button
        type="button"
        class="calendar-event-btn ${
          registered
            ? 'is-registered'
            : ''
        }"
        data-id="${id}"
        title="${esc(e.title || 'Evento')}"
      >
        ${esc(
          (e.time ? e.time + ' · ' : '') +
          (e.title || 'Evento')
        )}
      </button>
    `;
  }

  /*
   * ============================================================
   * ASOCIAR BOTONES
   * ============================================================
   */

  function bindButtons() {

    // Los eventos se regeneran con render(), por lo que usamos
    // delegación de eventos y lo registramos una sola vez.
    if (!list || list.dataset.nexusBound === '1') return;
    list.dataset.nexusBound = '1';

    list.addEventListener('click', event => {
      const button = event.target.closest('.event-signup, .calendar-event-btn');
      if (!button || !list.contains(button)) return;

      event.preventDefault();
      event.stopPropagation();

      const eventItem = getEvent(button.dataset.id);
      if (!eventItem) {
        console.error('NEXUS: no se encontró el evento', button.dataset.id);
        return;
      }

      openSignupModal(eventItem);
    });

    if (grid && grid.dataset.nexusBound !== '1') {
      grid.dataset.nexusBound = '1';
      grid.addEventListener('click', event => {
        const button = event.target.closest('.calendar-event-btn');
        if (!button || !grid.contains(button)) return;

        event.preventDefault();
        event.stopPropagation();

        const eventItem = getEvent(button.dataset.id);
        if (eventItem) openSignupModal(eventItem);
      });
    }
  }

  /*
   * ============================================================
   * CALENDARIO
   * ============================================================
   */

  function render() {

    if (!grid || !title || !list) {
      return;
    }

    const y =
      view.getFullYear();

    const m =
      view.getMonth();

    let monthTitle =
      new Intl.DateTimeFormat(
        'es-ES',
        {
          month: 'long',
          year: 'numeric'
        }
      ).format(view);

    title.textContent =
      monthTitle.charAt(0).toUpperCase() +
      monthTitle.slice(1);

    grid.innerHTML = '';

    const first =
      new Date(
        y,
        m,
        1
      );

    const start =
      (first.getDay() + 6) % 7;

    const days =
      new Date(
        y,
        m + 1,
        0
      ).getDate();

    const cells =
      Math.ceil(
        (start + days) / 7
      ) * 7;

    const today =
      iso(new Date());

    for (
      let i = 0;
      i < cells;
      i++
    ) {

      const n =
        i - start + 1;

      const d =
        new Date(
          y,
          m,
          n
        );

      const current =
        n >= 1 &&
        n <= days;

      const cell =
        document.createElement(
          'div'
        );

      cell.className =
        'day' +
        (
          current
            ? ''
            : ' other'
        ) +
        (
          iso(d) === today
            ? ' today'
            : ''
        );

      const dayNumber =
        document.createElement(
          'div'
        );

      dayNumber.className =
        'day-number';

      dayNumber.textContent =
        current
          ? n
          : (
              n < 1
                ? new Date(
                    y,
                    m,
                    0
                  ).getDate() + n
                : n - days
            );

      cell.appendChild(
        dayNumber
      );

      if (current) {

        events
          .filter(
            e =>
              e.date === iso(d)
          )
          .sort(
            (a, b) =>
              (a.time || '')
                .localeCompare(
                  b.time || ''
                )
          )
          .slice(0, 4)
          .forEach(e => {

            const wrap =
              document.createElement(
                'div'
              );

            wrap.className =
              'calendar-event-entry';

            wrap.innerHTML =
              eventAction(e);

            cell.appendChild(
              wrap
            );
          });
      }

      grid.appendChild(
        cell
      );
    }

    renderList(
      y,
      m
    );
  }

  /*
   * ============================================================
   * LISTA DE EVENTOS
   * ============================================================
   */

  function renderList(
    y,
    m
  ) {

    if (!list) {
      return;
    }

    list.innerHTML = '';

    const today =
      new Date();

    today.setHours(
      0,
      0,
      0,
      0
    );

    const arr =
      events
        .filter(e => {

          const d =
            new Date(
              (e.date || '') +
              'T00:00:00'
            );

          return (
            !Number.isNaN(
              d.getTime()
            ) &&
            d >= today
          );
        })
        .sort(
          (a, b) =>
            (
              a.date +
              (a.time || '')
            ).localeCompare(
              b.date +
              (b.time || '')
            )
        );

    if (!arr.length) {

      list.innerHTML =
        '<p class="event-empty">No hay próximos eventos.</p>';

      return;
    }

    arr
      .slice(0, 20)
      .forEach(e => {

        const item =
          document.createElement(
            'div'
          );

        item.className =
          'event-item';

        const d =
          new Date(
            e.date +
            'T00:00:00'
          );

        item.innerHTML = `
          <strong>
            ${esc(
              e.title || 'Evento'
            )}
          </strong>

          <small>
            ${d.toLocaleDateString(
              'es-ES',
              {
                weekday: 'short',
                day: 'numeric',
                month: 'short',
                year: 'numeric'
              }
            )}

            ${
              e.time
                ? ' · ' + esc(e.time)
                : ''
            }

            ${
              e.type
                ? ' · ' + esc(e.type)
                : ''
            }
          </small>

          ${
            e.description
              ? `
                <div
                  class="muted"
                  style="
                    font-size:.8rem;
                    margin-top:4px;
                  "
                >
                  ${esc(e.description)}
                </div>
              `
              : ''
          }

          <div
            style="
              margin-top:10px;
              display:flex;
              gap:8px;
              align-items:center;
              flex-wrap:wrap;
            "
          >

            ${signupButton(e)}

            ${
              e.discord_event_url
                ? `
                  <a
                    class="btn ghost"
                    target="_blank"
                    rel="noopener"
                    href="${esc(
                      e.discord_event_url
                    )}"
                  >
                    Discord
                  </a>
                `
                : ''
            }

          </div>
        `;

        list.appendChild(
          item
        );
      });

    bindButtons();
  }

  /*
   * ============================================================
   * NAVEGACIÓN DE MESES
   * ============================================================
   */

  const prev =
    document.getElementById(
      'prevMonth'
    );

  if (prev) {

    prev.addEventListener(
      'click',
      () => {

        view.setMonth(
          view.getMonth() - 1
        );

        render();
      }
    );
  }

  const next =
    document.getElementById(
      'nextMonth'
    );

  if (next) {

    next.addEventListener(
      'click',
      () => {

        view.setMonth(
          view.getMonth() + 1
        );

        render();
      }
    );
  }

  /*
   * ============================================================
   * INICIO
   * ============================================================
   */

  await loadData();

  updateAccount();

  render();

  grid.dataset.nexusCalendarInitialized = '1';
  delete grid.dataset.nexusCalendarInitializing;

};

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", NEXUS_CALENDAR_INIT, { once: true });
} else {
  NEXUS_CALENDAR_INIT();
}
window.addEventListener("nexus:page-loaded", NEXUS_CALENDAR_INIT);

})();
