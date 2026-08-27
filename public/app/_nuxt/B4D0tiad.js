import{ao as t}from"./DIofG46h.js";const u=()=>{const s=t(),r=n=>s.value?.permissions?.[n]===!0;return{can:r,canAny:(...n)=>n.some(e=>r(e)),canAll:(...n)=>n.every(e=>r(e))}};export{u};
