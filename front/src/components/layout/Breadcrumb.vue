<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const breadcrumbs = computed(() => {
  const pathArray = route.path.split('/').filter(item => item !== '');
  const breadcrumbsArray = [];
  let path = '';

  // Adiciona home
  breadcrumbsArray.push({
    name: 'Home',
    path: '/'
  });

  // Constrói os breadcrumbs baseados no caminho
  pathArray.forEach((item, index) => {
    path += `/${item}`;
    
    const formattedName = item.charAt(0).toUpperCase() + item.slice(1);
    
    breadcrumbsArray.push({
      name: formattedName,
      path: path,
      isLast: index === pathArray.length - 1
    });
  });

  return breadcrumbsArray;
});
</script>

<template>
  <nav class="flex mb-5" aria-label="Breadcrumb">
    <ol class="inline-flex items-center space-x-1 md:space-x-3">
      <li v-for="(crumb, index) in breadcrumbs" :key="index" class="inline-flex items-center">
        <template v-if="index === 0">
          <router-link :to="crumb.path" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white">
            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
              <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
            </svg>
            {{ crumb.name }}
          </router-link>
        </template>
        <template v-else>
          <div class="flex items-center">
            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
            </svg>
            <router-link 
              v-if="!crumb.isLast" 
              :to="crumb.path" 
              class="ml-1 text-sm font-medium text-gray-700 hover:text-primary-600 md:ml-2 dark:text-gray-400 dark:hover:text-white"
            >
              {{ crumb.name }}
            </router-link>
            <span v-else class="ml-1 text-sm font-medium text-gray-500 md:ml-2 dark:text-gray-400">{{ crumb.name }}</span>
          </div>
        </template>
      </li>
    </ol>
  </nav>
</template> 