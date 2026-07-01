;(function () {
    const interactiveSelector = [
        'a',
        'button',
        'input',
        'select',
        'textarea',
        'summary',
        '[contenteditable="true"]',
        '[role="button"]',
        '[role="menuitem"]',
        '[wire\\:click]',
        '[x-on\\:click]',
        '[data-filament-right-click-ignore]',
    ].join(',');

    const recordRowSelector = '.fi-ta-record, .fi-ta-row';

    const state = {
        activeIndex: -1,
        items: [],
        lastRow: null,
        menu: null,
        open: false,
        recordKey: null,
        table: null,
    };

    let listenersBound = false;

    function init(root = document) {
        ensureMenu();
        bindListeners();

        const table = root.matches?.('[data-filament-right-click-config]')
            ? root
            : root.querySelector?.('[data-filament-right-click-config]');

        if (table) {
            table.dataset.filamentRightClickReady = 'true';
        }
    }

    function bindListeners() {
        if (listenersBound) {
            return;
        }

        document.addEventListener('contextmenu', handleContextMenu);
        document.addEventListener('pointerover', rememberRow);
        document.addEventListener('focusin', rememberRow);
        document.addEventListener('click', handleDocumentClick);
        document.addEventListener('keydown', handleKeyDown);
        window.addEventListener('resize', closeMenu);
        window.addEventListener('scroll', closeMenu, true);
        document.addEventListener('livewire:navigated', closeMenu);

        listenersBound = true;
    }

    function ensureMenu() {
        if (state.menu?.isConnected) {
            return state.menu;
        }

        const menu = document.createElement('div');
        menu.className = 'fi-right-click-menu';
        menu.hidden = true;
        menu.setAttribute('role', 'menu');
        menu.setAttribute('aria-orientation', 'vertical');
        document.body.appendChild(menu);

        state.menu = menu;

        return menu;
    }

    function handleContextMenu(event) {
        const row = findRecordRow(event.target);

        if (! row || isInteractiveTarget(event.target)) {
            return;
        }

        const table = findEnabledTable(row);
        const config = table ? getConfig(table) : null;
        const recordKey = parseRecordKey(row);

        if (! table || ! config || ! recordKey || ! hasItems(config.items)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        openMenu({
            table,
            row,
            recordKey,
            items: config.items,
            x: event.clientX,
            y: event.clientY,
        });
    }

    function rememberRow(event) {
        const row = findRecordRow(event.target);

        if (! row || ! findEnabledTable(row) || ! parseRecordKey(row)) {
            return;
        }

        state.lastRow = row;
    }

    function handleDocumentClick(event) {
        if (! state.open || state.menu.contains(event.target)) {
            return;
        }

        closeMenu();
    }

    function handleKeyDown(event) {
        if (state.open) {
            handleOpenMenuKeyDown(event);

            return;
        }

        if (! isContextMenuKey(event)) {
            return;
        }

        const row = findRecordRow(event.target) || state.lastRow;
        const table = row ? findEnabledTable(row) : null;
        const config = table ? getConfig(table) : null;
        const recordKey = row ? parseRecordKey(row) : null;

        if (! row || ! table || ! config || ! recordKey || ! hasItems(config.items)) {
            return;
        }

        event.preventDefault();

        const rect = row.getBoundingClientRect();

        openMenu({
            table,
            row,
            recordKey,
            items: config.items,
            x: rect.left + 16,
            y: rect.top + Math.min(rect.height - 8, 24),
        });
    }

    function handleOpenMenuKeyDown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeMenu();

            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            focusRelativeItem(1);

            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            focusRelativeItem(-1);

            return;
        }

        if (event.key === 'Home') {
            event.preventDefault();
            focusItem(0);

            return;
        }

        if (event.key === 'End') {
            event.preventDefault();
            focusItem(getMenuButtons().length - 1);

            return;
        }

        if (event.key === 'Enter' || event.key === ' ') {
            const button = getMenuButtons()[state.activeIndex];

            if (! button) {
                return;
            }

            event.preventDefault();
            button.click();
        }
    }

    function isContextMenuKey(event) {
        return event.key === 'ContextMenu' || (event.shiftKey && event.key === 'F10');
    }

    function findRecordRow(target) {
        return target instanceof Element ? target.closest(recordRowSelector) : null;
    }

    function findEnabledTable(element) {
        return element.closest('[data-filament-right-click-config]');
    }

    function isInteractiveTarget(target) {
        return target instanceof Element && Boolean(target.closest(interactiveSelector));
    }

    function parseRecordKey(row) {
        const wireKey = row.getAttribute('wire:key');

        if (! wireKey) {
            return null;
        }

        const match = wireKey.match(/\.table\.records\.(.+)$/);

        return match?.[1] ?? null;
    }

    function getConfig(table) {
        const encodedConfig = table.dataset.filamentRightClickConfig;

        if (! encodedConfig) {
            return null;
        }

        if (table._filamentRightClickEncodedConfig === encodedConfig) {
            return table._filamentRightClickConfig;
        }

        try {
            const bytes = Uint8Array.from(atob(encodedConfig), character => character.charCodeAt(0));
            const config = JSON.parse(new TextDecoder().decode(bytes));

            table._filamentRightClickEncodedConfig = encodedConfig;
            table._filamentRightClickConfig = config;

            return config;
        } catch (error) {
            return null;
        }
    }

    function hasItems(items) {
        return Array.isArray(items) && items.some(item => {
            if (item.type === 'item') {
                return true;
            }

            if (item.type === 'section') {
                return hasItems(item.items);
            }

            return false;
        });
    }

    function openMenu({ table, row, recordKey, items, x, y }) {
        const menu = ensureMenu();

        state.table = table;
        state.recordKey = recordKey;
        state.items = flattenItems(items);
        state.activeIndex = -1;
        state.open = true;
        state.lastRow = row;

        renderMenu(menu, items);

        menu.hidden = false;
        menu.classList.add('fi-open');

        positionMenu(menu, x, y);
        focusItem(0);
    }

    function renderMenu(menu, entries) {
        menu.innerHTML = '';

        entries.forEach(entry => renderEntry(menu, entry));
    }

    function renderEntry(menu, entry) {
        if (entry.type === 'separator') {
            const separator = document.createElement('div');
            separator.className = 'fi-right-click-menu-separator';
            separator.setAttribute('role', 'separator');
            menu.appendChild(separator);

            return;
        }

        if (entry.type === 'section') {
            const section = document.createElement('div');
            section.className = 'fi-right-click-menu-section';

            if (entry.label) {
                const label = document.createElement('div');
                label.className = 'fi-right-click-menu-section-label';
                label.textContent = entry.label;
                section.appendChild(label);
            }

            if (Array.isArray(entry.items)) {
                entry.items.forEach(item => renderEntry(section, item));
            }

            menu.appendChild(section);

            return;
        }

        if (entry.type !== 'item') {
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'fi-right-click-menu-item';
        button.dataset.action = entry.action;
        button.dataset.target = entry.target || 'record';
        button.setAttribute('role', 'menuitem');
        button.tabIndex = -1;

        if (entry.color) {
            button.classList.add(`fi-color-${entry.color}`);
        }

        if (entry.icon) {
            const icon = document.createElement('span');
            icon.className = 'fi-right-click-menu-item-icon-ctn';
            icon.innerHTML = entry.icon;
            button.appendChild(icon);
        }

        const label = document.createElement('span');
        label.className = 'fi-right-click-menu-item-label';
        label.textContent = entry.label || entry.action;
        button.appendChild(label);

        button.addEventListener('click', () => triggerItem(entry));

        menu.appendChild(button);
    }

    function flattenItems(entries) {
        return entries.flatMap(entry => {
            if (entry.type === 'section') {
                return flattenItems(entry.items || []);
            }

            return entry.type === 'item' ? [entry] : [];
        });
    }

    function positionMenu(menu, x, y) {
        menu.style.left = '0px';
        menu.style.top = '0px';

        const rect = menu.getBoundingClientRect();
        const left = Math.min(x, window.innerWidth - rect.width - 8);
        const top = Math.min(y, window.innerHeight - rect.height - 8);

        menu.style.left = `${Math.max(8, left)}px`;
        menu.style.top = `${Math.max(8, top)}px`;
    }

    function triggerItem(item) {
        const { table, recordKey } = state;

        closeMenu();

        if (! table || ! recordKey || ! item.action) {
            return;
        }

        const wire = getWire(table);

        if (! wire) {
            return;
        }

        if (item.target === 'bulk') {
            triggerBulkAction(wire, table, recordKey, item.action);

            return;
        }

        callWire(wire, 'mountTableAction', [item.action, recordKey]);
    }

    function triggerBulkAction(wire, table, recordKey, action) {
        const selection = resolveBulkSelection(table, recordKey);

        syncBulkSelection(wire, selection);

        if (canCallWire(wire, 'mountAction')) {
            callWire(wire, 'mountAction', [
                action,
                {},
                { table: true, bulk: true },
            ]);

            return;
        }

        callWire(wire, 'mountTableBulkAction', [
            action,
            selection.isTrackingDeselectedRecords ? null : selection.selectedRecords,
        ]);
    }

    function resolveBulkSelection(table, recordKey) {
        const tableState = getTableState(table);

        if (tableState) {
            const isRecordSelected = isRecordSelectedInState(tableState, recordKey);

            if (isRecordSelected) {
                return {
                    isTrackingDeselectedRecords: Boolean(tableState.isTrackingDeselectedRecords),
                    selectedRecords: toArray(tableState.selectedRecords),
                    deselectedRecords: toArray(tableState.deselectedRecords),
                };
            }
        }

        const checkedRecordKeys = getCheckedRecordKeys(table);

        if (checkedRecordKeys.includes(recordKey)) {
            return {
                isTrackingDeselectedRecords: false,
                selectedRecords: checkedRecordKeys,
                deselectedRecords: [],
            };
        }

        return {
            isTrackingDeselectedRecords: false,
            selectedRecords: [recordKey],
            deselectedRecords: [],
        };
    }

    function getTableState(table) {
        if (typeof window.Alpine?.$data === 'function') {
            const tableState = window.Alpine.$data(table);

            if (tableState) {
                return tableState;
            }
        }

        return table._x_dataStack?.[0] ?? null;
    }

    function isRecordSelectedInState(tableState, recordKey) {
        if (typeof tableState.isRecordSelected === 'function') {
            return tableState.isRecordSelected(recordKey);
        }

        if (tableState.isTrackingDeselectedRecords) {
            return ! toArray(tableState.deselectedRecords).includes(recordKey);
        }

        return toArray(tableState.selectedRecords).includes(recordKey);
    }

    function getCheckedRecordKeys(table) {
        return Array.from(table.querySelectorAll('.fi-ta-record-checkbox:checked'))
            .map(checkbox => checkbox.value)
            .filter(value => value !== '');
    }

    function toArray(value) {
        if (value instanceof Set) {
            return [...value];
        }

        if (Array.isArray(value)) {
            return value;
        }

        if (value === null || value === undefined) {
            return [];
        }

        if (typeof value === 'string') {
            return [value];
        }

        return Array.from(value);
    }

    function syncBulkSelection(wire, selection) {
        setWireProperty(
            wire,
            'isTrackingDeselectedTableRecords',
            selection.isTrackingDeselectedRecords,
        );
        setWireProperty(wire, 'selectedTableRecords', selection.selectedRecords);
        setWireProperty(wire, 'deselectedTableRecords', selection.deselectedRecords);
    }

    function setWireProperty(wire, property, value) {
        if (typeof wire.set === 'function') {
            wire.set(property, value, false);

            return;
        }

        if (typeof wire.$set === 'function') {
            wire.$set(property, value, false);

            return;
        }

        wire[property] = value;
    }

    function getWire(table) {
        const livewireRoot = table.closest('[wire\\:id]');
        const componentId = livewireRoot?.getAttribute('wire:id');
        const component = componentId ? window.Livewire?.find(componentId) : null;

        return component?.$wire || component || null;
    }

    function canCallWire(wire, method) {
        return typeof wire[method] === 'function'
            || typeof wire.$call === 'function'
            || typeof wire.call === 'function';
    }

    function callWire(wire, method, parameters) {
        if (typeof wire[method] === 'function') {
            wire[method](...parameters);

            return;
        }

        if (typeof wire.$call === 'function') {
            wire.$call(method, ...parameters);

            return;
        }

        if (typeof wire.call === 'function') {
            wire.call(method, ...parameters);
        }
    }

    function focusRelativeItem(offset) {
        const buttons = getMenuButtons();

        if (! buttons.length) {
            return;
        }

        const nextIndex = (state.activeIndex + offset + buttons.length) % buttons.length;

        focusItem(nextIndex);
    }

    function focusItem(index) {
        const buttons = getMenuButtons();

        if (! buttons.length) {
            return;
        }

        state.activeIndex = Math.max(0, Math.min(index, buttons.length - 1));
        buttons[state.activeIndex]?.focus();
    }

    function getMenuButtons() {
        return Array.from(state.menu?.querySelectorAll('.fi-right-click-menu-item') || []);
    }

    function closeMenu() {
        if (! state.menu) {
            return;
        }

        state.menu.hidden = true;
        state.menu.classList.remove('fi-open');
        state.open = false;
        state.table = null;
        state.recordKey = null;
        state.items = [];
        state.activeIndex = -1;
    }

    window.FilamentRightClick = {
        init,
    };

    init();
})();
