<script setup>
import CrmCustomerProfileView from '@/views/crm/customers/CrmCustomerProfileView.vue'

definePage({
  meta: {
    action: 'read',
    subject: 'Contacts',
  },
})
</script>

<template>
  <CrmCustomerProfileView />
</template>
