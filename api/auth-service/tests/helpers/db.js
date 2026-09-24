const { PrismaClient } = require("@prisma/client");

const prisma = new PrismaClient();

const cleanDatabase = async () => {
  await prisma.refreshToken.deleteMany();
  await prisma.user.deleteMany();
};

module.exports = { prisma, cleanDatabase };
