<template>
    <InnovPanel>
        <InnovRow>
            <InnovCol>
                <button
                    v-if="showAddButton"
                    :disabled="mode !== 'list'"
                    @click="addItem"
                    class="button border border-blue-300 dark:border-blue-700 rounded-lg px-4 py-2 mb-2"
                >
                    <font-awesome-icon :icon="faPlus"/>
                    {{ addButtonText || $t('crud.newItem') }}
                </button>

                <Transition
                    name="fade"
                    mode="out-in"
                >
                    <div
                        v-if="mode === 'list'"
                        key="list"
                    >
                        <DataTable
                            ref="dataTableRef"
                            :data="[]"
                            :columns="columns"
                            :actions="actions"
                            :show-top-scrollbar="showTopScrollbar"
                            mode="remote"
                            :remoteConfig="remoteConfig"
                            :apiInstance="api"
                            @action-click="handleAction"
                            @loading="handleLoading"
                            @error="handleError"
                            @data-loaded="handleDataLoaded"
                            @filters-changed="handleFiltersChanged"
                        >
                            <!-- Passar todos os slots dinamicamente para o DataTable -->
                            <template
                                v-for="(_, name) in $slots"
                                :key="name"
                                #[name]="slotData"
                            >
                                <slot
                                    v-if="name !== 'form'"
                                    :name="name"
                                    v-bind="slotData"
                                ></slot>
                            </template>
                        </DataTable>
                    </div>
                    <div
                        v-else
                        key="form"
                    >
                        <form @submit.prevent>
                            <slot
                                name="form"
                                :item="currentItem"
                                :mode="mode"
                            >
                                <!-- Formulário padrão será renderizado aqui se não houver slot personalizado -->
                            </slot>

                            <ButtonsActions
                                :is-submitting="isSubmitting"
                                @submit="saveItem"
                                @cancel="cancelForm"
                            />

                        </form>
                    </div>
                </Transition>
            </InnovCol>
        </InnovRow>
    </InnovPanel>

    <!-- Modal de confirmação -->
    <InnovConfirm
        v-model="showConfirmModal"
        :title="confirmModalTitle || $t('crud.confirmDelete')"
        :message="confirmModalMessage || $t('crud.deleteWarning')"
        :confirm-text="confirmModalConfirmText || $t('common.delete')"
        :cancel-text="confirmModalCancelText || $t('common.cancel')"
        @confirm="confirmDelete"
    />
</template>

<script setup>
import {ref, computed, toRaw} from 'vue';
import {useI18n} from 'vue-i18n';
import DataTable from '@/components/data/DataTable.vue';
import InnovPanel from '@/components/base/InnovPanel.vue';
import InnovRow from '@/components/base/InnovRow.vue';
import InnovCol from '@/components/base/InnovCol.vue';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import {faPlus, faXmark} from '@fortawesome/free-solid-svg-icons';
import {innovToast} from '@/plugins/toast';
import InnovConfirm from '@/components/base/InnovConfirm.vue';
import {useAuthStore} from '@/stores/auth';
import ButtonsActions from "@/components/form/ButtonsActions.vue";
import {toFormData} from "@/composables/crud.js";

const {t} = useI18n();

const props = defineProps({
    // Configurações da API
    apiEndpoint: {
        type: String,
        required: true
    },
    api: {
        type: [Object, Function],
        required: true
    },

    // Configurações da tabela
    columns: {
        type: Array,
        required: true
    },
    actions: {
        type: Array,
        default: () => []
    },

    // Configurações do formulário
    defaultItem: {
        type: Object,
        default: () => ({})
    },

    // Configurações de UI
    showAddButton: {
        type: Boolean,
        default: true
    },
    addButtonText: {
        type: String,
        default: null
    },
    confirmModalTitle: {
        type: String,
        default: null
    },
    confirmModalMessage: {
        type: String,
        default: null
    },
    confirmModalConfirmText: {
        type: String,
        default: null
    },
    confirmModalCancelText: {
        type: String,
        default: null
    },
    showTopScrollbar: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits([
    'item-added',
    'item-updated',
    'item-deleted',
    'form-canceled',
    'action-clicked',
    'before-save',
    'filters-changed'
]);

// Estado local
const mode = ref('list');
const currentItem = ref({...props.defaultItem});
const showConfirmModal = ref(false);
const isSubmitting = ref(false);
const itemToDelete = ref(null);
const dataTableRef = ref(null);

// Computed para obter o tenant
const authStore = useAuthStore();
const tenant = computed(() => authStore.tenant || '');

// Configuração remota para o DataTable
const remoteConfig = computed(() => ({
    url: `/${tenant.value}${props.apiEndpoint}`,
    method: 'GET',
    params: {
        ascending: 1,
        byColumn: 1,
        filter: '{}'
    },
    headers: {},
    transformResponse: (data) => ({
        data: data.data || [],
        total: data.count || 0
    }),
    totalItemsKey: 'total',
    itemsKey: 'data'
}));

// Métodos
const addItem = () => {
    mode.value = 'form';
    currentItem.value = {...props.defaultItem};
};

const cancelForm = () => {
    mode.value = 'list';
    emit('form-canceled');
};

const saveItem = async () => {
    isSubmitting.value = true;
    try {
        let endpoint = props.apiEndpoint;

        if (endpoint.includes('?')) {
            endpoint = endpoint.split('?')[0];
        }

        const formData = toFormData(currentItem.value);
        let url = `/${tenant.value}${endpoint}`;

        if (currentItem.value.id) {
            url += `/${currentItem.value.id}`;
            formData.append('_method', 'PUT');
        }

        emit('before-save', {
            formData,
            currentItem: currentItem.value,
            isUpdate: Boolean(currentItem.value.id),
            endpoint: url
        });

        const response = await props.api.post(url, formData);
        emit('item-added', response.data);
        mode.value = 'list';
        innovToast('success', t('common.success'), t('crud.operationSuccess'));
        reloadData();
    } catch (error) {
        let errorMessage = '';
        for (let item in error.response?.data) {
            errorMessage = error.response?.data[item] + '\n';
        }
        innovToast('error', t('common.error'), errorMessage || t('crud.operationError'));
    } finally {
        isSubmitting.value = false
    }
};

const handleAction = async (action, item) => {
    if (action === 'edit') {
        currentItem.value = {...item};
        mode.value = 'form';
    } else if (action === 'delete') {
        itemToDelete.value = item;
        showConfirmModal.value = true;
    }

    const value = toRaw(item);
    emit('action-clicked', {action, value});
};

const updateItem = (item) => {
    currentItem.value = {...item};
    mode.value = 'form';
};

const deleteItem = (item) => {
    itemToDelete.value = item;
    showConfirmModal.value = true;
};

const confirmDelete = async () => {
    try {
        const endpoint = props.apiEndpoint.includes('?') ? props.apiEndpoint.split('?')[0] : props.apiEndpoint;
        await props.api.delete(`/${tenant.value}${endpoint}/${itemToDelete.value.id}`);
        innovToast('success', t('common.success'), t('crud.deleteSuccess'));
        emit('item-deleted', itemToDelete.value);
        reloadData();
    } catch (error) {
        const msg = error.response?.data?.message || error.response?.data || t('crud.deleteError');
        innovToast('error', t('common.error'), msg);
    }
};

const reloadData = () => {
    if (dataTableRef.value) {
        dataTableRef.value.loadRemoteData();
    }
};

// Handlers do DataTable
const handleLoading = (value) => {
    // Implementar se necessário
};

const handleError = (err) => {
    innovToast('error', 'Erro!', err.message || 'Erro ao carregar dados.');
};

const handleDataLoaded = (value) => {
    // Implementar se necessário
};

const handleFiltersChanged = (value) => {
    emit('filters-changed', value);
};

defineExpose({
    updateItem,
    deleteItem,
    reloadData
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style> 
