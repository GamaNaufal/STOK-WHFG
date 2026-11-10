import test from 'node:test';
import assert from 'node:assert/strict';
import {normalizeNavigationQuery, searchNavigation, nextNavigationSuggestion, readNavigationPreferences, initNavigation} from '../../public/js/layout-shell.js';

const features = [
    {label:'Rencana Delivery Ekspor', group:'Pengiriman Ekspor', route:'/plans', keywords:'rencana pengiriman'},
    {label:'Stok Ekspor', group:'Pengiriman Ekspor', route:'/stock', keywords:'stock stok'},
    {label:'Master Nomor Part Ekspor', group:'Master Data Ekspor', route:'/parts', keywords:'nomor no part part number'},
];
test('search matches separated words, their order, partial terms and familiar aliases', () => {
    for (const query of ['rencana ekspor', 'ekspor rencana', 'RENC eksp', 'rencana export']) {
        assert.equal(searchNavigation(features, query)[0]?.route, '/plans');
    }
    assert.equal(searchNavigation(features, 'stock export')[0]?.route, '/stock');
    assert.equal(searchNavigation(features, 'no part')[0]?.route, '/parts');
    assert.deepEqual(searchNavigation(features, 'tidak tersedia'), []);
    assert.equal(normalizeNavigationQuery('  PALÉT / lokal '), 'pallet lokal');
    assert.equal(normalizeNavigationQuery('  PALÉT / domestik '), 'pallet lokal');
    const planning = {label:'Perencanaan Pallet Ekspor',group:'Pengiriman Ekspor',route:'/pallets',keywords:'rencana'};
    assert.equal(searchNavigation([...features, planning], 'rencana ekspor')[0].route, '/plans');
});
test('recent suggestions cannot restore a revoked route and do not hide remaining menus', () => {
    const matches = searchNavigation(features, '', ['/forbidden', '/stock', '/stock']);
    assert.deepEqual(matches.map(item=>item.route), ['/stock', '/plans', '/parts']);
});
test('ArrowUp starts at the last result and both directions wrap correctly', () => {
    assert.equal(nextNavigationSuggestion(-1, 'up', 8), 7);
    assert.equal(nextNavigationSuggestion(-1, 'down', 8), 0);
    assert.equal(nextNavigationSuggestion(0, 'up', 8), 7);
    assert.equal(nextNavigationSuggestion(7, 'down', 8), 0);
    assert.equal(nextNavigationSuggestion(0, 'up', 0), -1);
});
test('unavailable or corrupt browser storage never prevents using navigation', () => {
    for (const storage of [undefined, {getItem(){throw Error('Denied');}}, {getItem:()=>'{bad json'}]) {
        assert.deepEqual(readNavigationPreferences(storage,'user'), {collapsed:false,groups:{},recent:[]});
    }
    assert.deepEqual(readNavigationPreferences({getItem:()=>JSON.stringify({collapsed:'yes',groups:{one:true,two:false,bad:'true'},recent:[null,'/ok']})},'user'),
        {collapsed:false,groups:{one:true,two:false},recent:['/ok']});
});

function shell(user = '1-admin', saved = {}) {
    const classList = () => {const values=new Set(); return {toggle(name,on){if(on)values.add(name);else values.delete(name);},contains:name=>values.has(name),remove:name=>values.delete(name)};};
    const control = () => ({attributes:{},listeners:{},classList:classList(),
        addEventListener(name,handler){this.listeners[name]=handler;},setAttribute(name,value){this.attributes[name]=value;},
        getAttribute(name){return this.attributes[name];},removeAttribute(name){delete this.attributes[name];},
        toggleAttribute(name,value){if(value)this.attributes[name]='';else delete this.attributes[name];}});
    const scroll = {...control(),clientHeight:400,scrollTop:0,getBoundingClientRect:()=>({top:64,bottom:464})};
    const active = {getBoundingClientRect:()=>({top:650-scroll.scrollTop,bottom:694-scroll.scrollTop})};
    const sidebar = {...control(),dataset:{navigationUser:user},querySelector:selector=>selector==='.offcanvas-body'?scroll:active,querySelectorAll:()=>[]};
    const toggle = control(), navbar = {...control(),getBoundingClientRect:()=>({height:64}),querySelector:()=>null};
    const body = {classList:classList(),style:{removeProperty(){}}};
    const documentRef = {body,activeElement:null,documentElement:{style:{setProperty(){}}},
        getElementById:id=>({'mobileSidebar':sidebar,'sidebarToggleBtn':toggle}[id]??null),
        querySelector:selector=>selector==='.top-navbar'?navbar:null,querySelectorAll:()=>[],addEventListener(){}};
    const values = new Map(Object.entries(saved)), session = new Map();
    const windowRef = {innerWidth:1280,localStorage:{getItem:key=>values.get(key)??null,setItem:(key,value)=>values.set(key,value)},
        sessionStorage:{getItem:key=>session.get(key)??null,setItem:(key,value)=>session.set(key,value)},
        addEventListener(){},requestAnimationFrame:callback=>callback()};
    initNavigation({documentRef,windowRef});
    return {sidebar,toggle,scroll,body,values,windowRef};
}
test('a hidden desktop sidebar is inert, has an accurate toggle state and can be reopened', () => {
    const ui=shell('1-admin', {'whfg.navigation.v2.1-admin':JSON.stringify({collapsed:true})});
    assert.equal(ui.body.classList.contains('sidebar-collapsed'),true);
    assert.equal(Object.hasOwn(ui.sidebar.attributes,'inert'),true);
    assert.equal(ui.toggle.attributes['aria-expanded'],'false');
    assert.equal(ui.body.classList.contains('navigation-motion'),false);
    ui.toggle.listeners.click();
    assert.equal(ui.body.classList.contains('navigation-motion'),true);
    assert.equal(Object.hasOwn(ui.sidebar.attributes,'inert'),false);
    assert.equal(ui.toggle.attributes['aria-expanded'],'true');
    assert.equal(JSON.parse(ui.values.get('whfg.navigation.v2.1-admin')).collapsed,false);
});
test('rapid sidebar toggles keep the final preference, focus guard and accessible state in agreement', () => {
    const ui=shell();
    for(let i=0;i<11;i++) ui.toggle.listeners.click();
    assert.equal(ui.toggle.attributes['aria-expanded'],'false');
    assert.equal(Object.hasOwn(ui.sidebar.attributes,'inert'),true);
    assert.equal(JSON.parse(ui.values.get('whfg.navigation.v2.1-admin')).collapsed,true);
    ui.toggle.listeners.click();
    assert.equal(ui.toggle.attributes['aria-expanded'],'true');
    assert.equal(Object.hasOwn(ui.sidebar.attributes,'inert'),false);
});
test('collapse preferences are isolated by account and role', () => {
    const ui=shell('2-export_operator', {'whfg.navigation.v2.1-admin':JSON.stringify({collapsed:true})});
    assert.equal(ui.body.classList.contains('sidebar-collapsed'),false);
    assert.equal(ui.toggle.attributes['aria-expanded'],'true');
});
test('the active destination is brought into view by scrolling only the sidebar container', () => {
    const ui=shell();
    assert.equal(ui.scroll.scrollTop,242);
    assert.equal(ui.body.classList.contains('sidebar-collapsed'),false);
});
