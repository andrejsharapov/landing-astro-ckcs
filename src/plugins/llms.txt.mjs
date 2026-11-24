import fs from 'node:fs';

const data = JSON.parse(fs.readFileSync('./src/data/landing.json', 'utf-8'));

// Достаём site из astro.config.mjs, чтобы не дублировать домен
const configRaw = fs.readFileSync('./astro.config.mjs', 'utf-8');
const siteMatch = configRaw.match(/site:\s*['"]([^'"]+)['"]/);
const siteUrl = (siteMatch ? siteMatch[1] : 'https://domen.site').replace(/\/$/, '');

const figuresList = data.figures
  .map((item) => `- **${item.name} (${item.size}):** ${item.price} — ${item.desc}`)
  .join('\n');

const featuresList = data.features
  .map((feat) => `- **${feat.title}:** ${feat.desc}`)
  .join('\n');

const faqList = data.faq.items
  .map((item) => `### ${item.q}\n${item.a}`)
  .join('\n\n');

const content = `# ${data.meta.brand} — ${data.meta.title}

> ${data.meta.description}

## О проекте и продукте
${data.about.lead}

${featuresList}

## Ассортимент и цены
${figuresList}

## Частые вопросы
${faqList}

## Как заказать
- Оформить заявку на сайте: [${siteUrl}/#order](${siteUrl}/#order)

## Контакты
[${siteUrl}](${siteUrl})
`;

fs.writeFileSync('./public/llms.txt', content, 'utf-8');
