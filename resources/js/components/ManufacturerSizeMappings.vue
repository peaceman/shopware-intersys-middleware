<template>
    <div>
        <h2>{{ $t('Size Mappings') }}</h2>

        <table class="table table-striped">
            <thead>
            <tr>
                <th>{{ $t('Gender') }}</th>
                <th>{{ $t('Source Size') }}</th>
                <th>{{ $t('Target Size') }}</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <manufacturer-size-mapping
                    v-for="sizeMapping in sizeMappings"
                    :key="sizeMapping.id"
                    :size-mapping="sizeMapping"
                    @save="(sm) => updateSizeMapping(sizeMapping.id, sm)"
                    @destroy="() => destroySizeMapping(sizeMapping.id)"
            />
            <manufacturer-size-mapping-new :manufacturer-id="manufacturerId" @created="() => loadSizeMappings()"/>
            </tbody>
        </table>
    </div>
</template>

<script>
    import axios from 'axios';
    import ManufacturerSizeMapping from './ManufacturerSizeMapping.vue';
    import ManufacturerSizeMappingNew from './ManufacturerSizeMappingNew.vue';

    export default {
        components: {
            ManufacturerSizeMapping,
            ManufacturerSizeMappingNew,
        },
        props: {
            manufacturerId: {
                type: Number,
                required: true
            }
        },
        data() {
            return {
                sizeMappings: [],
            };
        },
        created() {
            this.loadSizeMappings();
        },
        mounted() {
            console.log('Component mounted.')
        },
        methods: {
            loadSizeMappings() {
                axios.get(`/manufacturers/${this.manufacturerId}/size-mappings`)
                    .then(response => {
                        const sizeMappings = (response.data || {}).data || [];

                        this.sizeMappings = sizeMappings;
                    });
            },
            updateSizeMapping(sizeMappingId, sizeMapping) {
                axios.put(`/manufacturers/${this.manufacturerId}/size-mappings/${sizeMappingId}`, sizeMapping)
                    .then(response => {
                        const sizeMapping = (response.data || {}).data || {};
                        const smIndex = this.sizeMappings.findIndex(sm => sm.id == sizeMappingId);
                        this.sizeMappings.splice(smIndex, 1, sizeMapping);
                    });
            },
            destroySizeMapping(sizeMappingId) {
                axios.delete(`/manufacturers/${this.manufacturerId}/size-mappings/${sizeMappingId}`)
                    .then(() => {
                        const smIndex = this.sizeMappings.findIndex(sm => sm.id == sizeMappingId);
                        this.sizeMappings.splice(smIndex, 1);
                    });
            }
        }
    }
</script>
