<template>
    <InnovRow>
        <InnovCol>
            <button
                @click="$emit('submit')"
                class="button border border-blue-300 dark:border-blue-700 rounded-lg px-4 py-2 mb-2 mt-2 cursor-pointer"
                type="submit"
                :disabled="isSubmitting"
            >
                <font-awesome-icon :icon="faFloppyDisk"/>
                {{ isSubmitting ? $t('form.saving') : (isEditing ? $t('common.update') : $t('common.save')) }}
            </button>
            <button
                type="button"
                @click="$emit('cancel')"
                class="ml-2 button border border-blue-300 dark:border-blue-700 rounded-lg px-4 py-2 mb-2 cursor-pointer"
            >
                <font-awesome-icon :icon="faXmark"/>
                {{ $t('common.cancel') }}
            </button>
        </InnovCol>
    </InnovRow>
</template>

<script setup>
import InnovRow from '@/components/base/InnovRow.vue';
import InnovCol from '@/components/base/InnovCol.vue';
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import {faXmark, faFloppyDisk} from '@fortawesome/free-solid-svg-icons';

const props = defineProps({
    isEditing: {
        type: Boolean,
        default: false
    },
    isSubmitting: {
        type: Boolean,
        default: false
    }
});

defineEmits([
    'submit',
    'cancel',
]);

</script>
