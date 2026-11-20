
import { CATEGORY_IMAGE_RULES } from '../data/category-images';
import { Report } from '../models';

export function categoryImage(r: Pick<Report, 'category'>): string {

  const hit = CATEGORY_IMAGE_RULES.find(([re]) => re.test(r.category));
  return `images/report-${hit?.[1] ?? 'cleanup'}.jpg`;
}

export function reportImage(r: Pick<Report, 'imageUrl' | 'category'>): string {
  return r.imageUrl || categoryImage(r);
}
