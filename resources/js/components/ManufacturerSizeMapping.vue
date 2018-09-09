<template>
    <tr>
        <td>
            <input type="text" class="form-control-plaintext" readonly :value="sm.gender">
        </td>
        <td>
            <input type="text" class="form-control-plaintext" readonly :value="sm.source_size">
        </td>
        <td>
            <input class="form-control" type="text" v-model="sm.target_size">
        </td>
        <td class="d-flex justify-content-end">
            <button class="btn btn-danger mx-1" @click="destroy">{{ $t('Delete') }}</button>
            <button class="btn btn-primary mx-1" :disabled="isPristine" @click="save">{{ $t('Save') }}</button>
        </td>
    </tr>
</template>

<script>
    export default {
        props: {
            sizeMapping: {
                type: Object,
                required: true,
            },
        },
        data() {
            return {
                sm: {...this.sizeMapping},
            };
        },
        computed: {
            isPristine() {
                return this.sizeMapping.target_size == this.sm.target_size;
            },
        },
        methods: {
            save() {
                this.$emit('save', {...this.sm});
            },
            destroy() {
                this.$emit('destroy', {...this.sm});
            }
        }
    };
</script>
