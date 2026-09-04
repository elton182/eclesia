<template>
  <div class="transition-all duration-300 ">
      <!-- Cabeçalho com filtros -->
      <div class="flex flex-col md:flex-row justify-between mb-4 gap-3">
          <!-- Pesquisa global menor -->
          <div class="flex-1 max-w-xs">
              <div class="relative">
                  <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                      <svg
                          class="w-4 h-4 text-gray-500 dark:text-gray-400"
                          aria-hidden="true"
                          xmlns="http://www.w3.org/2000/svg"
                          fill="none"
                          viewBox="0 0 20 20"
                      >
                          <path
                              stroke="currentColor"
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"
                          />
                      </svg>
                  </div>
                  <input
                      type="text"
                      v-model="searchTerm"
                      class="block w-full p-2 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                      :placeholder="$t('dataTable.globalSearch')"
                  >
              </div>
          </div>

          <!-- Botão de filtro por coluna -->
          <div class="flex items-center gap-2">
              <button
                  @click="showFilters = !showFilters"
                  class="px-4 py-2 text-sm font-medium text-blue-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-blue-400 dark:hover:bg-gray-700"
              >
                  <span
                      v-if="appliedFilters > 0"
                      class="mr-1 px-1.5 py-0.5 bg-blue-500 text-white rounded-full text-xs"
                  >{{ appliedFilters }}
                  </span>
                  {{ $t('dataTable.filters') }}
              </button>

              <!-- Seletor de itens por página -->
              <div class="flex items-center gap-2">
                  <label
                      for="itemsPerPage"
                      class="text-sm text-gray-600 dark:text-gray-400"
                  >{{ $t('dataTable.items') }}:
                  </label>
                  <select
                      id="itemsPerPage"
                      v-model="localItemsPerPage"
                      class="p-1.5 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                  >
                      <option value="5">5</option>
                      <option value="10">10</option>
                      <option value="25">25</option>
                      <option value="50">50</option>
                      <option value="100">100</option>
                  </select>
              </div>
          </div>
      </div>

      <!-- Filtros por coluna expandidos -->
      <div
          v-if="showFilters"
          class="mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600"
      >
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
              <div
                  v-for="column in filterableColumns"
                  :key="column.key"
                  :class="getFilterColumnClass(column)"
              >
                  <SelectSearch
                      v-if="column.type==='select'"
                      :options="column.options"
                      v-model="columnFilters[column.key]"
                      :label="$t(column.label)"
                      :name="column.key"
                      :multiple="column.multiple ?? false"
                  />
                  <TextInput
                      v-else
                      :id="column.key"
                      type="text"
                      :label="$t(column.label)"
                      v-model="columnFilters[column.key]"
                      :name="columnFilters[column.key]"
                      :placeholder="`${$t('common.filter')} ${$t(column.label).toLowerCase()}`"
                      :mask="column.mask || ''"
                  />
              </div>
          </div>
          <div class="flex justify-end mt-3">
              <button
                  @click="clearFilters"
                  class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
              >
                  {{ $t('dataTable.clearFilters') }}
              </button>
          </div>
      </div>

      <!-- Barra de rolagem horizontal superior -->
      <div
          v-if="showTopScrollbar"
          ref="topScrollRef"
          class="overflow-x-scroll overflow-y-hidden mb-2 h-4"
          @scroll="syncScrollFromTop"
      >
          <div
              ref="topScrollContentRef"
              class="h-1"
          ></div>
      </div>

      <!-- Tabela de dados -->
      <div
          ref="bottomScrollRef"
          class="overflow-x-auto relative"
          @scroll="syncScrollFromBottom"
      >
          <div
              v-if="isLoading"
              class="absolute inset-0 bg-white/50 dark:bg-gray-800/50 flex items-center justify-center z-10"
          >
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
          </div>

          <div
              v-if="error"
              class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-200 dark:text-red-800"
          >
              {{ error }}
          </div>

          <table
              ref="tableRef"
              class="w-full text-sm text-left text-gray-600 dark:text-gray-400"
          >
              <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-300 rounded-lg">
              <tr>
                  <th
                      v-for="column in columns"
                      :key="column.key"
                      @click="column.sortable === false ? null : sort(column.key)"
                      scope="col"
                      class="px-4 py-3 hover:bg-gray-100 dark:hover:bg-gray-600"
                      :class="[
                          column.sortable === false ? 'cursor-default' : 'cursor-pointer',
                          { 'rounded-l-lg': column === columns[0], 'rounded-r-lg': column === columns[columns.length-1] }
                      ]"
                  >
                      <div class="flex items-center">
                          {{ $t(column.label) }}
                          <span
                              v-if="sortColumn === column.key"
                              class="ml-1"
                          >
                              <span v-if="sortDirection === 'asc'">▲</span>
                              <span v-else>▼</span>
                          </span>
                      </div>
                  </th>
                  <th
                      v-if="actions.length > 0 || $slots.actions"
                      class="px-4 py-3"
                  >{{ $t('common.actions') }}
                  </th>
              </tr>
              </thead>
              <tbody>
              <tr
                  v-for="(item, index) in paginatedData"
                  :key="index"
                  class="bg-white dark:bg-gray-800 border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200"
              >
                  <td
                      v-for="column in columns"
                      :key="column.key"
                      class="px-4 py-3"
                      :class="{ 'font-medium text-gray-900 dark:text-white': column === columns[0] }"
                  >
                      <slot
                          v-if="$slots[`cell-${column.key}`]"
                          :name="`cell-${column.key}`"
                          :row="item"
                          :column="column"
                          :value="item[column.key]"
                      ></slot>
                      <span v-else-if="column.formatter">
                          {{ column.formatter(item) }}
                      </span>
                      <span v-else-if="column.component">
                          <component
                              :is="column.component"
                              v-bind="column.props ? column.props(item) : {}"
                          />
                      </span>
                      <span v-else>
                          {{ item[column.key] }}
                      </span>
                  </td>
                  <td
                      v-if="actions.length > 0 || $slots.actions"
                      class="px-4 py-3"
                  >
                      <slot
                          name="actions"
                          :item="item"
                      ></slot>
                      <div
                          v-if="!$slots.actions"
                          class="flex items-center gap-2"
                      >
                          <button
                              v-for="action in actions"
                              :key="action.key"
                              @click="emit('action-click', action.key, item)"
                              class="button p-1.5 "
                              :class="action.color ? `text-${action.color}-500` : ''"
                              :title="action.label"
                          >
                              <font-awesome-icon :icon="action.icon"/>
                          </button>
                      </div>
                  </td>
              </tr>
              <tr v-if="paginatedData.length === 0">
                  <td
                      :colspan="columns.length + (actions.length > 0 || $slots.actions ? 1 : 0)"
                      class="px-4 py-3 text-center text-gray-500 dark:text-gray-400"
                  >
                      {{ $t('dataTable.noResults') }}
                  </td>
              </tr>
              </tbody>
          </table>
      </div>

      <!-- Paginação aprimorada -->
      <div
          class="flex flex-col md:flex-row justify-between items-center mt-4 gap-4"
          v-if="(props.mode === 'remote' ? totalRemoteItems : (filteredData ? filteredData.length : 0)) > 0"
      >
          <div class="text-sm text-gray-700 dark:text-gray-300">
              {{ $t('dataTable.showing') }}
              <span class="font-medium">{{ startIndex + 1 }}</span>
              {{ $t('dataTable.to') }}
              <span class="font-medium">{{ endIndex }}</span>
              {{ $t('dataTable.of') }}
              <span class="font-medium">
                  {{ props.mode === 'remote' ? totalRemoteItems : (filteredData ? filteredData.length : 0) }}
              </span>
              {{ $t('dataTable.results') }}
          </div>

          <div class="inline-flex -space-x-px">
              <button
                  @click="prevPage"
                  :disabled="currentPage === 1"
                  class="px-3 py-2 ml-0 leading-tight text-gray-500 bg-white border border-gray-300 rounded-l-lg hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white disabled:opacity-50 disabled:cursor-not-allowed"
              >
                  {{ $t('dataTable.previous') }}
              </button>

              <button
                  v-if="currentPage > 3"
                  @click="goToPage(1)"
                  class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
              >
                  1
              </button>

              <button
                  v-if="currentPage > 4"
                  class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400"
                  disabled
              >
                  ...
              </button>

              <button
                  v-for="pageNumber in visiblePageNumbers"
                  :key="pageNumber"
                  @click="goToPage(pageNumber)"
                  :class="[
            'px-3 py-2 leading-tight border',
            currentPage === pageNumber
              ? 'text-blue-600 border-blue-300 bg-blue-50 hover:bg-blue-100 hover:text-blue-700 dark:border-gray-700 dark:bg-gray-700 dark:text-white'
              : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'
          ]"
              >
                  {{ pageNumber }}
              </button>

              <button
                  v-if="currentPage < totalPages - 3"
                  class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400"
                  disabled
              >
                  ...
              </button>

              <button
                  v-if="totalPages > 3 && currentPage < totalPages - 2"
                  @click="goToPage(totalPages)"
                  class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
              >
                  {{ totalPages }}
              </button>

              <button
                  @click="nextPage"
                  :disabled="currentPage >= totalPages"
                  class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 rounded-r-lg hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white disabled:opacity-50 disabled:cursor-not-allowed"
              >
                  {{ $t('dataTable.next') }}
              </button>
          </div>
      </div>
  </div>
</template>

<script setup>
import {ref, computed, watch, onMounted, onBeforeUnmount, nextTick} from 'vue';
import { useI18n } from 'vue-i18n';

import api from '@/services/api';
import SelectSearch from '@/components/form/SelectSearch.vue';
import TextInput from '@/components/form/TextInput.vue';

const { t } = useI18n();

// Props
const props = defineProps({
  data: {type: Array, default: () => []},
  columns: {type: Array, required: true},
  itemsPerPage: {type: Number, default: 10},
  actions: {type: Array, default: () => []},
  mode: {
      type: String,
      default: 'local',
      validator: (v) => ['local', 'remote'].includes(v),
  },
  remoteConfig: {
      type: Object,
      default: () => ({
          url: '',
          method: 'GET',
          params: {},
          headers: {},
          transformResponse: (data) => data,
          totalItemsKey: 'total',
          itemsKey: 'data',
      }),
  },
  apiInstance: {type: [Object, Function], required: true},
  showTopScrollbar: {type: Boolean, default: false},
});

// Emits
const emit = defineEmits(['action-click', 'loading', 'error', 'data-loaded', 'filters-changed']);

// State
const searchTerm = ref('');
const currentPage = ref(1);
const sortColumn = ref('');
const sortDirection = ref('desc');
const showFilters = ref(false);
const columnFilters = ref({});
const localItemsPerPage = ref(props.itemsPerPage);
const isLoading = ref(false);
const error = ref(null);
const remoteData = ref([]);
const appliedFilters = ref(0);
const totalRemoteItems = ref(0);
const topScrollRef = ref(null);
const bottomScrollRef = ref(null);
const topScrollContentRef = ref(null);
const tableRef = ref(null);
const hasHorizontalOverflow = ref(false);
const syncingScroll = ref(null);
let resizeObserver = null;

// Debounce
let debounceTimeout = null;

function debounce(func, delay = 500) {
  return function (...args) {
      clearTimeout(debounceTimeout);
      debounceTimeout = setTimeout(() => func.apply(this, args), delay);
  };
}

watch(() => props.itemsPerPage, (nv) => {
  localItemsPerPage.value = nv;
  currentPage.value = 1;
});

function getFilters() {
  return columnFilters.value;
}

const lastUnfilteredCache = ref([]);

// ---------- Helpers para busca global (JSON cru + valores renderizados) ----------
const rowFlatCache = new WeakMap();

function flattenValues(obj, {exclude = [], maxLen = 2000, depth = 0} = {}) {
  if (obj == null) return '';
  if (depth > 6) return '';
  const excludeSet = new Set([
      ...exclude,
      'photo', 'photo_raw', 'barcode', 'sign_url', 'term_signature_photo',
  ]);
  const parts = [];
  const walk = (val, d = 0) => {
      if (val == null) return;
      const t = typeof val;
      if (t === 'string' || t === 'number' || t === 'boolean') {
          const s = String(val);
          if (s.trim()) parts.push(s);
          return;
      }
      if (Array.isArray(val)) {
          for (const v of val) walk(v, d + 1);
          return;
      }
      if (t === 'object') {
          for (const [k, v] of Object.entries(val)) {
              if (!excludeSet.has(k)) walk(v, d + 1);
          }
      }
  };
  walk(obj, depth);
  const joined = parts.join(' ').toLowerCase();
  return joined.length > maxLen ? joined.slice(0, maxLen) : joined;
}

function getRowFlatString(row) {
  if (rowFlatCache.has(row)) return rowFlatCache.get(row);
  const flat = flattenValues(row);
  rowFlatCache.set(row, flat);
  return flat;
}

function normalize(v) {
  if (v === true) return 1;
  if (v === false) return 0;
  if (typeof v === 'string' && /^-?\d+$/.test(v)) return Number(v);
  return v;
}

function getRenderedValue(row, column) {
  try {
      if (typeof column.formatter === 'function') {
          const out = column.formatter(row);
          return out == null ? '' : String(out);
      }
      if (column.type === 'select' && Array.isArray(column.options)) {
          const rv = normalize(row?.[column.key]);
          const opt = column.options.find(o => normalize(o.value) === rv);
          if (opt) return String(opt.label ?? '');
      }
      const raw = row?.[column.key];
      return raw == null ? '' : String(raw);
  } catch {
      return '';
  }
}

function getRenderedRowString(row) {
  return (props.columns || [])
      .map(c => getRenderedValue(row, c))
      .join(' ')
      .toLowerCase();
}

function getRowSearchString(row) {
  return (getRowFlatString(row) + ' ' + getRenderedRowString(row)).toLowerCase();
}

// -----------------------------------------------------------------------------

// Remote loader
async function loadRemoteData() {
  if (props.mode !== 'remote') return;

  isLoading.value = true;
  error.value = null;
  emit('loading', true);

  const getVal = (obj, key) => {
      if (!obj || !key) return undefined;
      return key.split('.').reduce((acc, k) => (acc ? acc[k] : undefined), obj);
  };

  try {
      const {
          url,
          method = 'get',
          params = {},
          headers = {},
          transformResponse = (d) => d,
          totalItemsKey = 'total',
          itemsKey = 'data',
      } = props.remoteConfig || {};

      // Filtros de coluna enviados ao server
      const processedColumnFilters = {};
      Object.entries(columnFilters.value || {}).forEach(([key, filterValue]) => {
          if (filterValue !== null && filterValue !== undefined && String(filterValue) !== '') {
              const column = (props.columns || []).find((col) => col.key === key);
              const filterKey = column?.filterKey || key;
              processedColumnFilters[filterKey] = filterValue;
          }
      });

      const sortColumnValue = !sortColumn.value || String(sortColumn.value).trim() === '' ? 'id' : sortColumn.value;

      const baseParams = {
          page: currentPage.value,
          limit: localItemsPerPage.value,
          sort_column: sortColumnValue,
          sort_direction: sortDirection.value,
          ...params,
          ...processedColumnFilters,
      };
      if (sortDirection.value === 'asc') baseParams.orderByAsc = sortColumnValue;
      else baseParams.orderByDesc = sortColumnValue;

      const term = (searchTerm.value || '').toLowerCase().trim();

      const requestOnce = async (customParams = {}) => {
          const axiosConfig = {
              url,
              method: method.toLowerCase(),
              headers: {'Content-Type': 'application/json', ...headers},
          };
          const payload = {...baseParams, ...customParams};
          if (axiosConfig.method === 'get') axiosConfig.params = payload;
          else axiosConfig.data = payload;
          const resp = await props.apiInstance.request(axiosConfig);
          const t = transformResponse(resp.data) || {};
          return {
              rows: Array.isArray(t[itemsKey]) ? t[itemsKey] : [],
              total:
                  typeof t[totalItemsKey] === 'number'
                      ? t[totalItemsKey]
                      : Array.isArray(t[itemsKey])
                          ? t[itemsKey].length
                          : 0,
          };
      };

      // Primeira tentativa com query no servidor
      let server = await requestOnce({query: term || undefined});

      // Fallback para filtrar localmente
      if (term && server.rows.length === 0) {
          if (lastUnfilteredCache.value.length === 0) {
              const bigLimit = Math.max(500, localItemsPerPage.value);
              const all = await requestOnce({query: undefined, page: 1, limit: bigLimit});
              lastUnfilteredCache.value = all.rows;
          }
          server = {rows: lastUnfilteredCache.value, total: lastUnfilteredCache.value.length};
      } else if (!term) {
          lastUnfilteredCache.value = server.rows;
      }

      // Filtro local com base no que é RENDERIZADO + JSON cru
      let rows = server.rows;
      if (term) {
          rows = rows.filter(r => getRowSearchString(r).includes(term));

          // ordenação client-side
          const dir = sortDirection.value === 'desc' ? -1 : 1;
          rows = [...rows].sort((a, b) => {
              const va = getVal(a, sortColumnValue);
              const vb = getVal(b, sortColumnValue);
              if (va == null && vb == null) return 0;
              if (va == null) return -1 * dir;
              if (vb == null) return 1 * dir;
              if (va > vb) return 1 * dir;
              if (va < vb) return -1 * dir;
              return 0;
          });

          // paginação client-side
          const start = (currentPage.value - 1) * localItemsPerPage.value;
          rows = rows.slice(start, start + localItemsPerPage.value);
      }

      // Estado
      remoteData.value = rows;
      totalRemoteItems.value = term
          ? (server.rows ? server.rows.filter(r => getRowSearchString(r).includes(term)).length : 0)
          : server.total;

      await nextTick();
      syncTopScrollbar();

      emit('data-loaded', {data: remoteData.value, total: totalRemoteItems.value});
  } catch (err) {
      console.error('Erro ao carregar dados:', err);
      error.value = err?.message || 'Erro desconhecido';
      emit('error', err);
  } finally {
      isLoading.value = false;
      emit('loading', false);
  }
}

function syncTopScrollbar() {
  if (!props.showTopScrollbar) return;

  const tableEl = tableRef.value;
  const topContentEl = topScrollContentRef.value;
  const bottomEl = bottomScrollRef.value;

  if (!tableEl || !topContentEl || !bottomEl) return;

  const tableWidth = tableEl.scrollWidth || 0;
  const bottomWidth = bottomEl.scrollWidth || 0;
  const scrollWidth = Math.max(tableWidth, bottomWidth);
  topContentEl.style.width = `${scrollWidth}px`;
  hasHorizontalOverflow.value = scrollWidth > (bottomEl.clientWidth + 1);

  if (topScrollRef.value && topScrollRef.value.scrollLeft !== bottomEl.scrollLeft) {
      topScrollRef.value.scrollLeft = bottomEl.scrollLeft;
  }
}

function syncScrollFromTop() {
  if (!topScrollRef.value || !bottomScrollRef.value) return;
  if (syncingScroll.value === 'bottom') return;

  syncingScroll.value = 'top';
  bottomScrollRef.value.scrollLeft = topScrollRef.value.scrollLeft;
  requestAnimationFrame(() => {
      syncingScroll.value = null;
  });
}

function syncScrollFromBottom() {
  if (!topScrollRef.value || !bottomScrollRef.value) return;
  if (syncingScroll.value === 'top') return;

  syncingScroll.value = 'bottom';
  topScrollRef.value.scrollLeft = bottomScrollRef.value.scrollLeft;
  requestAnimationFrame(() => {
      syncingScroll.value = null;
  });
}

// Computed para colunas filtráveis
const filterableColumns = computed(() => {
  return props.columns.filter(col => col.filterable !== false);
});

// Função para obter as classes CSS da largura do filtro
function getFilterColumnClass(column) {
  const width = column.filterWidth || column.width || 1;

  // Mapeia larguras para classes do grid
  const widthClasses = {
      1: 'col-span-1',
      2: 'md:col-span-2',
      3: 'md:col-span-3',
      4: 'md:col-span-4',
      'full': 'col-span-full'
  };

  return widthClasses[width] || 'col-span-1';
}

// Mounted
onMounted(() => {
  // inicia filtros de coluna (apenas para colunas filtráveis)
  filterableColumns.value.forEach(column => {
      if (column.type === 'select' || column.type === 'text' || !column.type) {
          columnFilters.value[column.key] = column.multiple ? [] : '';
      }
  });

  if (props.mode === 'remote') {
      loadRemoteData();
  }

  nextTick(syncTopScrollbar);

  if (typeof ResizeObserver !== 'undefined') {
      resizeObserver = new ResizeObserver(() => syncTopScrollbar());
      if (tableRef.value) resizeObserver.observe(tableRef.value);
      if (bottomScrollRef.value) resizeObserver.observe(bottomScrollRef.value);
  }

  window.addEventListener('resize', syncTopScrollbar);
});

onBeforeUnmount(() => {
  if (resizeObserver) {
      resizeObserver.disconnect();
      resizeObserver = null;
  }
  window.removeEventListener('resize', syncTopScrollbar);
});

const debouncedLoadRemoteData = debounce(loadRemoteData, 500);

watch([currentPage, localItemsPerPage, searchTerm, sortColumn, sortDirection], () => {
  if (props.mode === 'remote') {
      debouncedLoadRemoteData();
  }
  nextTick(syncTopScrollbar);
});

watch(() => columnFilters.value, () => {
  let count = 0;
  Object.keys(columnFilters.value).forEach(key => {
      if ((!Array.isArray(columnFilters.value[key]) && columnFilters.value[key] && columnFilters.value[key] !== '') ||
          (Array.isArray(columnFilters.value[key]) && columnFilters.value[key].length > 0)) {
          count++;
      }
  });
  appliedFilters.value = count;
  emit('filters-changed', {filters: {...columnFilters.value}});

  if (props.mode === 'remote') {
      currentPage.value = 1;
      debouncedLoadRemoteData();
  }
  nextTick(syncTopScrollbar);
}, {deep: true});

watch(() => props.columns, () => {
  nextTick(syncTopScrollbar);
}, {deep: true});

// Local mode filtering/sorting/paging
const filteredData = computed(() => {
  if (props.mode === 'remote') return remoteData.value;

  let result = props.data;

  // busca global (usa o renderizado também)
  if (searchTerm.value) {
      const term = searchTerm.value.toLowerCase().trim();
      result = result.filter(row => getRowSearchString(row).includes(term));
  }

  // filtros por coluna (mantém sua lógica simples)
  Object.entries(columnFilters.value).forEach(([key, filterValue]) => {
      if (filterValue) {
          result = result.filter(item => {
              const value = item[key];
              if (value === null || value === undefined) return false;
              return String(value).toLowerCase().includes(String(filterValue).toLowerCase());
          });
      }
  });

  return result;
});

const sortedData = computed(() => {
  if (!sortColumn.value) return filteredData.value;
  return [...filteredData.value].sort((a, b) => {
      const aValue = a[sortColumn.value];
      const bValue = b[sortColumn.value];
      if (aValue === bValue) return 0;
      const comparison = aValue > bValue ? 1 : -1;
      return sortDirection.value === 'asc' ? comparison : -comparison;
  });
});

const totalPages = computed(() => {
  if (props.mode === 'remote') return Math.ceil(totalRemoteItems.value / localItemsPerPage.value);
  return filteredData.value ? Math.ceil(filteredData.value.length / localItemsPerPage.value) : 1;
});

const startIndex = computed(() => (currentPage.value - 1) * localItemsPerPage.value);

const endIndex = computed(() => {
  const end = startIndex.value + localItemsPerPage.value;
  if (props.mode === 'remote') {
      return end <= totalRemoteItems.value ? end : totalRemoteItems.value;
  } else {
      const totalItems = filteredData.value ? filteredData.value.length : 0;
      return end <= totalItems ? end : totalItems;
  }
});

const paginatedData = computed(() => {
  if (props.mode === 'remote') return remoteData.value;
  return sortedData.value ? sortedData.value.slice(startIndex.value, endIndex.value) : [];
});

watch(paginatedData, () => {
  nextTick(syncTopScrollbar);
});

const visiblePageNumbers = computed(() => {
  if (totalPages.value <= 7) {
      return Array.from({length: totalPages.value}, (_, i) => i + 1);
  }
  const current = currentPage.value;
  if (current <= 3) {
      return [1, 2, 3, 4, 5];
  } else if (current >= totalPages.value - 2) {
      return Array.from({length: 5}, (_, i) => totalPages.value - 4 + i);
  } else {
      return [current - 2, current - 1, current, current + 1, current + 2];
  }
});

// Actions
function sort(column) {
  if (sortColumn.value === column) {
      sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
  } else {
      sortColumn.value = column;
      sortDirection.value = 'desc';
  }
}

function nextPage() {
  if (currentPage.value < totalPages.value) currentPage.value++;
}

function prevPage() {
  if (currentPage.value > 1) currentPage.value--;
}

function goToPage(page) {
  if (page >= 1 && page <= totalPages.value) currentPage.value = page;
}

function clearFilters() {
  filterableColumns.value.forEach(column => {
      if (Object.prototype.hasOwnProperty.call(columnFilters.value, column.key)) {
          columnFilters.value[column.key] = column.multiple ? [] : '';
      }
  });
  searchTerm.value = '';
}

// Expose
defineExpose({loadRemoteData, getFilters});
</script>

