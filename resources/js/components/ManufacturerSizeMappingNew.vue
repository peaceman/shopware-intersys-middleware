<template>
    <tr>
        <td>
            <select class="form-control" :class="{'is-invalid': errors.gender}" v-model="sizeMapping.gender">
                <option v-for="gender in genders" :value="gender">{{ gender }}</option>
            </select>
            <div class="invalid-feedback">
                <span v-for="e in errors.gender">{{ e }}</span>
            </div>
        </td>
        <td>
            <input class="form-control" type="text" :class="{'is-invalid': errors.source_size}" v-model="sizeMapping.source_size">
            <div class="invalid-feedback">
                <span v-for="e in errors.source_size">{{ e }}</span>
            </div>
        </td>
        <td>
            <input class="form-control" type="text" :class="{'is-invalid': errors.target_size}" v-model="sizeMapping.target_size">
            <div class="invalid-feedback">
                <span v-for="e in errors.target_size">{{ e }}</span>
            </div>
        </td>
        <td class="d-flex justify-content-end">
            <button class="btn btn-primary mx-1" @click="create">{{ $t('Create') }}</button>
        </td>
    </tr>
</template>

<script>
    import axios from 'axios';

    export default {
        props: {
            manufacturerId: {
                type: Number,
                required: true,
            },
        },
        data() {
            return {
                genders: ['male', 'female', 'child'],
                sizeMapping: {},
                errors: {},
            };
        },
        computed: {

        },
        methods: {
            create() {
                axios.post(`/manufacturers/${this.manufacturerId}/size-mappings`, this.sizeMapping)
                    .then(() => {
                        this.errors = {};
                        this.sizeMapping = {};
                        this.$emit('created');
                    })
                    .catch(error => {
                        if (!error.response) {
                            this.errors.general = this.$t('An error occurred');
                        } else {
                            this.errors = error.response.data.errors;
                        }
                    });
            },
        }
    };
</script>
