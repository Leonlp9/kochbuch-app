import { createRouter, createWebHashHistory } from 'vue-router'

// Hash-History: funktioniert problemlos in der Capacitor-App (file://-Kontext).
const router = createRouter({
  history: createWebHashHistory(),
  scrollBehavior() {
    return { top: 0 }
  },
  routes: [
    { path: '/', name: 'home', component: () => import('@/views/HomeView.vue') },
    { path: '/search', name: 'search', component: () => import('@/views/SearchView.vue') },
    {
      path: '/recipe/:id',
      name: 'recipe',
      component: () => import('@/views/RecipeView.vue'),
      props: true,
    },
    {
      path: '/new',
      name: 'new',
      component: () => import('@/views/RecipeEditView.vue'),
    },
    {
      path: '/edit/:id',
      name: 'edit',
      component: () => import('@/views/RecipeEditView.vue'),
      props: true,
    },
    {
      path: '/manage/categories',
      name: 'categories',
      component: () => import('@/views/CategoriesView.vue'),
    },
    {
      path: '/manage/ingredients',
      name: 'ingredients',
      component: () => import('@/views/IngredientsView.vue'),
    },
    {
      path: '/manage/appliances',
      name: 'appliances',
      component: () => import('@/views/AppliancesView.vue'),
    },
    {
      path: '/calendar',
      name: 'calendar',
      component: () => import('@/views/CalendarView.vue'),
    },
    {
      path: '/settings',
      name: 'settings',
      component: () => import('@/views/SettingsView.vue'),
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'notfound',
      component: () => import('@/views/NotFoundView.vue'),
    },
  ],
})

export default router
