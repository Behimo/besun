<script setup>
defineProps({
  teamConversations: { type: Array, default: () => [] },
  groupConversations: { type: Array, default: () => [] },
  dmConversations: { type: Array, default: () => [] },
  active: { type: Object, required: true },
})

const emit = defineEmits(['select', 'create-group'])
</script>

<template>
  <div class="team-chat-sidebar__header">
    <div class="d-flex align-center justify-space-between mb-1">
      <h5 class="text-h6 mb-0">
        گفتگو
      </h5>
      <VBtn
        icon
        size="small"
        variant="tonal"
        @click="emit('create-group')"
      >
        <VIcon icon="tabler-users-plus" />
        <VTooltip
          activator="parent"
          location="bottom"
        >
          گروه جدید
        </VTooltip>
      </VBtn>
    </div>
    <p class="text-caption text-medium-emphasis mb-0">
      تیم، گروه‌ها و پیام خصوصی
    </p>
  </div>

  <div class="team-chat-sidebar__scroll">
    <VList
      nav
      density="compact"
      class="pa-2"
    >
      <VListSubheader class="text-uppercase text-caption">
        عمومی
      </VListSubheader>
      <VListItem
        v-for="conv in teamConversations"
        :key="conv.id"
        :active="active.type === conv.type && active.id === conv.id"
        rounded
        prepend-icon="tabler-users-group"
        :title="conv.title"
        @click="emit('select', conv)"
      />

      <VListSubheader class="text-uppercase text-caption mt-2">
        گروه‌ها
      </VListSubheader>
      <VListItem
        v-for="conv in groupConversations"
        :key="`g-${conv.id}`"
        :active="active.type === 'group' && active.id === conv.id"
        rounded
        @click="emit('select', conv)"
      >
        <template #prepend>
          <VAvatar
            color="info"
            variant="tonal"
            size="34"
          >
            <VIcon
              icon="tabler-hash"
              size="18"
            />
          </VAvatar>
        </template>
        <VListItemTitle>{{ conv.title }}</VListItemTitle>
        <VListItemSubtitle>{{ conv.members_count }} عضو</VListItemSubtitle>
      </VListItem>
      <div
        v-if="!groupConversations.length"
        class="text-caption text-medium-emphasis px-4 pb-2"
      >
        گروهی وجود ندارد
      </div>

      <VListSubheader class="text-uppercase text-caption mt-2">
        پیام خصوصی
      </VListSubheader>
      <VListItem
        v-for="conv in dmConversations"
        :key="`d-${conv.id}`"
        :active="active.type === 'dm' && active.id === conv.id"
        rounded
        @click="emit('select', conv)"
      >
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            size="34"
          >
            <span class="text-caption">{{ conv.title?.charAt(0) ?? '?' }}</span>
          </VAvatar>
        </template>
        <VListItemTitle>{{ conv.title }}</VListItemTitle>
        <VListItemSubtitle class="text-truncate">
          {{ conv.email }}
        </VListItemSubtitle>
      </VListItem>
    </VList>
  </div>
</template>
